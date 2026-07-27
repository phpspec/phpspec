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

    let('profile', fn() => new CommandProfile(
        name: 'generate',
        body: 'THE-BODY',
        tools: ['write_feature', 'write_steps', 'propose_edit'],
        answer: 'tool_call',
        grounding: ['recency', 'tree', 'named_files'],
        temperature: 0.2,
    ));

    let('config', fn(Filesystem $fs) => new Configuration('.', $fs));

    it('consults the model for a named new feature and keeps the derived path over its content', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'write_feature', ['content' => "Feature: Adding tasks\n  Scenario: Adds a task\n    Given an empty list\n    When I add \"milk\"\n    Then the list holds it"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->do($this->profile, 'a simple scenario in features/user_adds_tasks.feature');

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

        $outcome = $agent->do($this->profile, 'a spec for a Coupon that reduces a total');

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

        $agent->do($this->profile, 'a spec for a Coupon');

        expect($replay->requests[0]['options']['toolChoice'])->toBe('required');
    });

    it('forces the one declared tool by name when the manifest declares exactly one', function (Filesystem $fs) {
        $profile = new CommandProfile(name: 'next', body: 'ADVISE', tools: ['suggest_next'], answer: 'tool_call');
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'suggest_next', ['type' => 'spec', 'target' => 'App\\Coupon', 'reason' => 'ok'])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->do($profile, '');

        expect($replay->requests[0]['options']['toolChoice'])->toBe(['name' => 'suggest_next']);
    });

    it('lets the user config max_tokens beat the manifest and the default', function (Filesystem $fs) {
        $yamlPath = './phpspec.yaml';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $yamlPath);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $yamlPath ? "ai:\n  provider: google\n  api_key: k\n  max_tokens: 9999\n" : '');
        $profile = new CommandProfile(name: 'generate', body: '', tools: ['propose_edit'], answer: 'tool_call', maxTokens: 4096);
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent(new Configuration('.', $fs), $fs, $replay);

        $agent->do($profile, 'a spec for a Coupon');

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

        $agent->do($this->profile, 'a spec for a Coupon');

        expect($replay->requests[0]['options']['effort'])->toBe('high');
    });

    it('falls back from the manifest max_tokens to it before any code default', function (Filesystem $fs) {
        $profile = new CommandProfile(name: 'generate', body: '', tools: ['propose_edit'], answer: 'tool_call', maxTokens: 4096);
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->do($profile, 'a spec for a Coupon');

        expect($replay->requests[0]['options']['maxTokens'])->toBe(4096);
    });

    it('sends no toolChoice for a prose command', function (Filesystem $fs) {
        $profile = new CommandProfile(name: 'chat', body: 'TALK', tools: [], answer: 'prose');
        $replay = new ReplayProvider([new Response('some advice')]);
        $agent = new Agent($this->config, $fs, $replay);

        $agent->do($profile, 'what next?');

        expect($replay->requests[0]['options'])->not()->toHaveKey('toolChoice');
    });

    it('surfaces a provider failure as prose instead of crashing the command', function (Filesystem $fs) {
        $agent = new Agent($this->config, $fs, new AgentSpecThrowingProvider());

        $outcome = $agent->do($this->profile, 'a spec for a Coupon');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->toContain('API key not valid');
    });

    it('re-asks once when a tool_call command is answered in prose', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('here is some chatty prose instead'),
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->do($this->profile, 'a spec for a Coupon');

        expect($replay->requests)->toHaveLength(2);
        expect($outcome->proposals[0]->path)->toBe('spec/Coupon.spec.php');
    });

    it('fails cleanly when the model never answers on the tool channel', function (Filesystem $fs) {
        $replay = new ReplayProvider([new Response('prose'), new Response('more prose')]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->do($this->profile, 'a spec for a Coupon');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->not()->toBe('');
        expect($replay->requests)->toHaveLength(2);
    });

    it('surfaces a tool rejection as the outcome prose', function (Filesystem $fs) {
        $replay = new ReplayProvider([
            new Response('', [new ToolCall('1', 'propose_edit', ['path' => 'spec/Calc.spec.php', 'content' => "<?php\ndescribe('Calc', function () { it('adds', function () { \$this->add(2)->shouldReturn(2); }); });"])]),
        ]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->do($this->profile, 'a spec for a Calc');

        expect($outcome->proposals)->toBe([]);
        expect($outcome->prose)->toContain('ObjectBehavior');
    });

    it('surfaces a deterministic refusal as the outcome prose', function (Filesystem $fs) {
        $agent = new Agent($this->config, $fs, new ReplayProvider());

        $outcome = $agent->do($this->profile, 'the steps for features/missing.feature');

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

        $agent->do($this->profile, 'change Calc to do something');

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

        $agent->do($this->profile, 'a spec for a Coupon');

        $captures = array_filter(array_keys($this->written), fn(string $path) => str_ends_with($path, '.phpspec/ai/last-request.json'));

        expect($captures)->toHaveLength(1);
    });

});
