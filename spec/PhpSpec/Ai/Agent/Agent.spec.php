<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Transcript;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\Role;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// A provider whose chat always fails, the way a live one does on a bad key or
// an unenforceable toolChoice (papi-core 0.13 throws before any HTTP call).
class AgentSpecThrowingProvider implements PhpSpec\Ai\Contracts\ProviderInterface
{
    public function chat(array $messages, array $options = []): Response
    {
        throw new RuntimeException('Google API error (400): API key not valid.');
    }
}

// A scripted live-session seam: records what the loop executes, optionally ends
// the turn with a hand-back line, exactly the contract pair's executor fulfils.
class AgentSpecScriptedExecutor implements PhpSpec\Ai\Contracts\ToolExecutor
{
    /** @var list<string> */
    public array $executed = [];

    public ?string $handBackAfter = null;

    public ?string $correction = null;

    public ?array $suggestion = null;

    public function beginTurn(): void
    {
    }

    public function advertised(): array
    {
        return [];
    }

    public function execute(PhpSpec\Ai\ToolCall $call): mixed
    {
        $this->executed[] = $call->name;

        return 'ok:' . $call->name;
    }

    public function observations(): array
    {
        return [];
    }

    public function turnComplete(Response $response): ?string
    {
        return $this->handBackAfter;
    }

    public function correction(Response $response): ?string
    {
        $correction = $this->correction;
        $this->correction = null;

        return $correction;
    }

    public function lastSuggestion(): ?array
    {
        return $this->suggestion;
    }
}

// The one verb every AI command calls: ground, resolve the step, act
// deterministically when the step fully determines the artifact, otherwise ask
// the model on the declared answer channel (with one corrective re-ask), and
// capture the whole exchange either way.
describe(Agent::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        $this->written = [];
        $written = &$this->written;
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });
    });

    let('config', fn(Filesystem $fs) => new Configuration('.', $fs));

    it('consults the model for a named new feature and keeps the derived path over its content', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'write_feature', ['content' => "Feature: Adding tasks\n  Scenario: Adds a task\n    Given an empty list\n    When I add \"milk\"\n    Then the list holds it"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->chat('generate', 'a simple scenario in features/user_adds_tasks.feature');

        expect($replay->requests)->toHaveLength(1);
        expect($outcome->step->phase)->toBe(Phase::WriteFeature);
        expect($outcome->proposals[0]->path)->toBe('features/user_adds_tasks.feature');
        expect($outcome->proposals[0]->new)->toContain('When I add "milk"');
    });

    it('asks the model on the tool channel and lands its call as a proposal', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/App/Whatever.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->chat('generate', 'a spec for a Coupon that reduces a total');

        expect($outcome->proposals[0]->path)->toBe('spec/Coupon.spec.php');
        expect($replay->requests)->toHaveLength(1);
        expect($replay->requests[0]['messages'][1]->content)->toContain('a spec for a Coupon');
        expect($replay->requests[0]['options']['temperature'])->toBe(0.2);
        expect($replay->requests[0]['options']['tools'])->toHaveLength(3);
    });

    it('forces the tool channel via toolChoice: required with several tools declared', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('generate', 'a spec for a Coupon');

        expect($replay->requests[0]['options']['toolChoice'])->toBe('required');
    });

    it('forces the one declared tool by name when the manifest declares exactly one', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'suggest_next', ['type' => 'spec', 'target' => 'App\\Coupon', 'reason' => 'ok'])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('next', '');

        expect($replay->requests[0]['options']['toolChoice'])->toBe(['name' => 'suggest_next']);
    });

    it('lets the user config max_tokens beat the manifest and the default', function (Filesystem $fs) {
        $yamlPath = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: k\n  max_tokens: 9999\n" : '');
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);

        $agent->chat('generate', 'a spec for a Coupon');

        expect($replay->requests[0]['options']['maxTokens'])->toBe(9999);
    });

    it('passes the configured reasoning effort through to the provider', function (Filesystem $fs) {
        $yamlPath = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: k\n  effort: high\n" : '');
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);

        $agent->chat('generate', 'a spec for a Coupon');

        expect($replay->requests[0]['options']['effort'])->toBe('high');
    });

    it('falls back from the manifest max_tokens to it before any code default', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('generate', 'a spec for a Coupon');

        expect($replay->requests[0]['options']['maxTokens'])->toBe(16384);   // the shipped generate manifest
    });

    it('sends no toolChoice for a prose command, resolved from a project prompt', function (Filesystem $fs) {
        $override = getcwd() . '/.phpspec/prompts/commands/advice.txt';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $override);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $override ? 'TALK to the human.' : '');
        $replay = new ReplayProvider([new Response('some advice')]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('advice', 'what next?');

        expect($replay->requests[0]['options'])->not()->toHaveKey('toolChoice');
        expect($replay->requests[0]['messages'][0]->content)->toContain('TALK to the human.');
    });

    it('folds a project override over the shipped manifest per key', function (Filesystem $fs) {
        $override = getcwd() . '/.phpspec/prompts/commands/generate.txt';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $override);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $override ? "---\ntemperature: 0.9\n---\nOUR house rules." : '');
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('generate', 'a spec for a Coupon');

        expect($replay->requests[0]['options']['temperature'])->toBe(0.9);        // the project's key
        expect($replay->requests[0]['options']['tools'])->toHaveLength(3);        // the shipped tools survive
        expect($replay->requests[0]['messages'][0]->content)->toContain('OUR house rules.');
    });

    it('reports an unknown command as prose instead of crashing', function (Filesystem $fs) {
        $agent = new Agent($this->config, $fs, new ReplayProvider());

        $outcome = $agent->chat('nonsense', 'anything');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->toContain('Unknown AI command "nonsense"');
    });

    it('surfaces a provider failure as prose instead of crashing the command', function (Filesystem $fs) {
        $agent = new Agent($this->config, $fs, new AgentSpecThrowingProvider());

        $outcome = $agent->chat('generate', 'a spec for a Coupon');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->toContain('API key not valid');
    });

    it('stands in with the feature skeleton when the provider fails on a write-feature ask', function (Filesystem $fs) {
        $agent = new Agent($this->config, $fs, new AgentSpecThrowingProvider());

        $outcome = $agent->chat('generate', 'a feature for completing_a_task.feature');

        expect($outcome->proposals[0]->path)->toBe('features/completing_a_task.feature');
        expect($outcome->proposals[0]->new)->toContain('Feature:');
        expect($outcome->prose)->toContain('API key not valid');   // the failure stays visible beside the stand-in
    });

    it('re-asks once when a tool_call command is answered in prose', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('here is some chatty prose instead'),
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->chat('generate', 'a spec for a Coupon');

        expect($replay->requests)->toHaveLength(2);
        expect($outcome->proposals[0]->path)->toBe('spec/Coupon.spec.php');
    });

    it('fails cleanly when the model never answers on the tool channel', function (Filesystem $fs) {
        $replay = new ReplayProvider([new Response('prose'), new Response('more prose')]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->chat('generate', 'a spec for a Coupon');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->not()->toBe('');
        expect($replay->requests)->toHaveLength(2);
    });

    it('surfaces a tool rejection as the outcome prose', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Calc.spec.php', 'content' => "<?php\ndescribe('Calc', function () { it('adds', function () { \$this->add(2)->shouldReturn(2); }); });"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->chat('generate', 'a spec for a Calc');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->toContain('ObjectBehavior');
    });

    it('surfaces a deterministic refusal as the outcome prose', function (Filesystem $fs) {
        $agent = new Agent($this->config, $fs, new ReplayProvider());

        $outcome = $agent->chat('generate', 'the steps for features/missing.feature');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->toContain('features/missing.feature');
    });

    it('feeds the model the project tree and the files the instruction names', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '/src') || str_ends_with($p, 'src/Calc.php'));
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '/src'));
        allow($fs->scandir())->toReturn(['Calc.php']);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, 'Calc.php') ? '<?php class Calc {}' : '');
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'src/Calc.php', 'content' => '<?php // updated'])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('generate', 'change Calc to do something');

        $context = $replay->requests[0]['messages'][1]->content;
        expect($context)->toContain('Calc.php');            // the tree
        expect($context)->toContain('class Calc');          // the named file's contents
        expect($context)->toContain('change Calc to do something');
    });

    it('captures every exchange to the debug recording', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->chat('generate', 'a spec for a Coupon');

        $captures = array_filter(array_keys($this->written), fn(string $path) => str_ends_with($path, '.phpspec/ai/last-request.json'));

        expect($captures)->toHaveLength(1);
    });

    context('conversation: a persistent transcript and a live executor', function () {

        it('keeps one system message across two chats on the same transcript', function (Filesystem $fs) {
            $replay = new ReplayProvider([new Response('first answer'), new Response('second answer')]);
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: new AgentSpecScriptedExecutor());

            $agent->chat('navigator', 'hello');
            $agent->chat('navigator', 'and again');

            $first = $replay->requests[0]['messages'];
            $second = $replay->requests[1]['messages'];

            expect(count(array_filter($second, fn($message) => $message->role === Role::System)))->toBe(1);
            expect($second[0]->content)->toBe($first[0]->content);
            expect(end($second)->content)->toContain('and again');
            expect(count($second))->toBe(count($first) + 2);
        });

        it('executes tool calls through the executor and feeds results back until prose', function (Filesystem $fs) {
            $replay = new ReplayProvider([
                new Response('', [new ToolCall('t1', 'run_specs', ['path' => ''])]),
                new Response('all green'),
            ]);
            $executor = new AgentSpecScriptedExecutor();
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: $executor);

            $outcome = $agent->chat('navigator', 'run the suite');

            expect($executor->executed)->toBe(['run_specs']);
            expect($replay->requests)->toHaveLength(2);
            $toolResults = array_values(array_filter($replay->requests[1]['messages'], fn($message) => $message->role === Role::Tool));
            expect($toolResults[0]->content)->toBe('ok:run_specs');
            expect($toolResults[0]->toolCallId)->toBe('t1');
            expect($outcome->prose)->toBe('all green');
        });

        it('ends the turn with the executor\'s hand-back line', function (Filesystem $fs) {
            $replay = new ReplayProvider([
                new Response('', [new ToolCall('t1', 'run_specs', ['path' => ''])]),
                new Response('never reached'),
            ]);
            $executor = new AgentSpecScriptedExecutor();
            $executor->handBackAfter = 'That is my one change for this turn.';
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: $executor);

            $outcome = $agent->chat('navigator', 'do the thing');

            expect($replay->requests)->toHaveLength(1);
            expect($outcome->prose)->toBe('That is my one change for this turn.');
        });

        it('re-asks the round the session says was misdelivered', function (Filesystem $fs) {
            $replay = new ReplayProvider([
                new Response('Shall I offer the updated spec?'),
                new Response('here is the change'),
            ]);
            $executor = new AgentSpecScriptedExecutor();
            $executor->correction = 'Make the offer now with offer_change.';
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: $executor);

            $outcome = $agent->chat('navigator', 'what next?');

            expect($replay->requests)->toHaveLength(2);
            expect((string) json_encode($replay->requests[1]['messages']))->toContain('Make the offer now with offer_change.');
            expect($outcome->prose)->toBe('here is the change');
        });

        it('ends a prose turn the session has nothing to correct', function (Filesystem $fs) {
            $replay = new ReplayProvider([new Response('what should the argument be?'), new Response('never reached')]);
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: new AgentSpecScriptedExecutor());

            $outcome = $agent->chat('navigator', 'what next?');

            expect($replay->requests)->toHaveLength(1);
            expect($outcome->prose)->toBe('what should the argument be?');
        });

        it('re-orients the system slot when the command changes mid-transcript', function (Filesystem $fs) {
            $replay = new ReplayProvider([new Response('as navigator'), new Response('as driver')]);
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: new AgentSpecScriptedExecutor());

            $agent->chat('navigator', 'hello');
            $agent->chat('driver', 'you drive now');

            $second = $replay->requests[1]['messages'];

            expect($second[0]->role)->toBe(Role::System);
            expect($second[0]->content)->toContain('DRIVER');
            expect(count(array_filter($second, fn($message) => $message->role === Role::System)))->toBe(1);
            expect($second[1]->content)->toContain('hello');
        });

        it('grounds the suite state once: the situation message carries it, the context block does not', function (Filesystem $fs) {
            $replay = new ReplayProvider([new Response('understood')]);
            $agent = new Agent($this->config, $fs, $replay, transcript: new Transcript(), executor: new AgentSpecScriptedExecutor());
            $suite = new SuiteSummary(
                'red',
                ['examples' => 3, 'passes' => 2, 'failures' => 1, 'errors' => 0, 'pending' => 0],
                [],
                [],
                ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 1, 'undefined' => 0],
                [['path' => 'features/adding.feature', 'status' => 'red', 'undefined' => 0]],
            );

            $agent->chat('navigator', 'what now?', new Grounding(suite: $suite));

            $messages = $replay->requests[0]['messages'];
            $situations = array_values(array_filter($messages, fn($message) => is_string($message->content) && str_starts_with($message->content, '[Current situation]')));
            expect($situations)->toHaveLength(1);
            expect($situations[0]->content)->toContain('FEATURES: 1 features');
            expect(end($messages)->content)->not()->toContain('# Suite state');
        });

        it('never executes a tool call without an executor: the call stays a proposal', function (Filesystem $fs) {
            $replay = new ReplayProvider([
                new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'src/App/A.php', 'content' => '<?php class A {}'])]),
                new Response('never reached'),
            ]);
            $agent = new Agent($this->config, $fs, $replay);

            $outcome = $agent->chat('generate', 'update src/App/A.php please');

            expect($replay->requests)->toHaveLength(1);
            expect($outcome->proposals)->toHaveLength(1);
            $captures = array_filter(array_keys($this->written), fn(string $path) => str_ends_with($path, '.phpspec/ai/last-request.json'));
            expect(array_keys($this->written))->toBe(array_values($captures));
        });

    });

});
