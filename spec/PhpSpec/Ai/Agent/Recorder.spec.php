<?php

use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Recorder;
use PhpSpec\Ai\Agent\Request;
use PhpSpec\Ai\Agent\Step;
use PhpSpec\Ai\Response;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Filesystem;

// The debug log IS an eval recording: every exchange lands in
// .phpspec/ai/last-request.json in the eval-recording schema, so promoting a
// misbehaviour to a permanent regression fixture is one copy. Credentials are
// never written.
describe(Recorder::class, function () {

    beforeEach(function (Filesystem $fs) {
        $this->written = [];
        $written = &$this->written;
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });
    });

    it('captures every round of a multi-round turn', function (Filesystem $fs) {
        $recorder = new Recorder($fs, '/proj');

        $recorder->capture('navigator', 'run the suite', null, null, ['provider' => 'google'], new Response('all green'), [], [
            ['response' => new Response('', [new ToolCall('t1', 'run_specs', ['path' => ''])]), 'tool_results' => ['t1' => 'SUITE: green']],
            ['response' => new Response('all green')],
        ]);

        $doc = json_decode($this->written['/proj/.phpspec/ai/last-request.json'] ?? '', true);

        expect($doc['rounds'])->toHaveLength(2);
        expect($doc['rounds'][0]['response']['tool_calls'][0]['name'])->toBe('run_specs');
        expect($doc['rounds'][0]['tool_results']['t1'])->toBe('SUITE: green');
        expect($doc['response']['text'])->toBe('all green');
    });

    it('captures the exchange to .phpspec/ai/last-request.json in the recording schema', function (Filesystem $fs) {
        $recorder = new Recorder($fs, '/proj');
        $step = new Step(Phase::WriteSteps, null, 'features/adding.feature', 'steps are undefined');
        $request = new Request('SYSTEM', 'CONTEXT', ['commands/generate']);
        $response = new Response('ok', [new ToolCall('1', 'write_steps', ['feature_path' => 'features/adding.feature'])]);

        $recorder->capture('generate', 'the steps', $step, $request, ['provider' => 'google', 'api_key' => 'SECRET'], $response, [
            new Proposal('features/steps/adding.steps.php', '', '<?php', true, 'write_steps'),
        ]);

        $json = $this->written['/proj/.phpspec/ai/last-request.json'] ?? '';
        $doc = json_decode($json, true);

        expect($doc['command'])->toBe('generate');
        expect($doc['instruction'])->toBe('the steps');
        expect($doc['step']['phase'])->toBe('write-steps');
        expect($doc['step']['because'])->toBe('steps are undefined');
        expect($doc['request']['system'])->toBe('SYSTEM');
        expect($doc['request']['composed_from'])->toBe(['commands/generate']);
        expect($doc['response']['text'])->toBe('ok');
        expect($doc['response']['tool_calls'][0]['name'])->toBe('write_steps');
        expect($doc['proposals'][0]['path'])->toBe('features/steps/adding.steps.php');
    });

    it('never writes credentials into the capture', function (Filesystem $fs) {
        $recorder = new Recorder($fs, '/proj');

        $recorder->capture('generate', 'x', null, null, ['provider' => 'google', 'api_key' => 'SECRET'], null);

        expect($this->written['/proj/.phpspec/ai/last-request.json'])->not()->toContain('SECRET');
        expect($this->written['/proj/.phpspec/ai/last-request.json'])->toContain('google');
    });

    it('captures a deterministic run with no model exchange at all', function (Filesystem $fs) {
        $recorder = new Recorder($fs, '/proj');
        $step = new Step(Phase::WriteSteps, null, 'features/adding.feature', 'steps are undefined');

        $recorder->capture('generate', 'the steps', $step, null, ['provider' => 'google', 'api_key' => 'k'], null, [
            new Proposal('features/steps/adding.steps.php', '', '<?php', true, 'write_steps'),
        ]);

        $doc = json_decode($this->written['/proj/.phpspec/ai/last-request.json'], true);

        expect($doc['response'])->toBeNull();
        expect($doc['proposals'][0]['origin'])->toBe('write_steps');
    });

});
