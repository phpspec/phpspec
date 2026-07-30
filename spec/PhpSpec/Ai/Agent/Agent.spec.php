<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
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

});
