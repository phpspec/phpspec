<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

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

    it('acts deterministically for a fully determined step, never consulting the model', function (Filesystem $fs) {
        $replay = new ReplayProvider([new Response('should not be used')]);
        $agent = new Agent($this->config, $fs, $replay);

        $outcome = $agent->do($this->profile, 'a simple scenario in features/user_adds_tasks.feature');

        expect($replay->requests)->toBe([]);
        expect($outcome->step->phase)->toBe(Phase::WriteFeature);
        expect($outcome->proposals[0]->path)->toBe('features/user_adds_tasks.feature');
        expect($outcome->proposals[0]->new)->toContain('Feature:');
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
