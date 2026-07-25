<?php

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

require_once __DIR__ . '/../ReplayProvider.php';

// E5 & E6 (evals, adversarial) — when the model proposes a spec in phpspec-8
// ObjectBehavior syntax (a `->shouldReturn()` matcher, or a method called on
// the subject `$this`), the scaffold must never propose it. The pipeline
// rejects the tool call and surfaces the reason as prose instead of writing
// DSL that would fatal when phpspec loads it.
describe('E5/E6 generate: reject ObjectBehavior spec syntax', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
    });

    $reject = function (string $case) {
        return function (Filesystem $fs) use ($case) {
            $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/' . $case . '.json'), true);
            $replay = ReplayProvider::fromRecording($rec);

            $agent = new Agent(new Configuration('.', $fs), $fs, $replay);
            $outcome = $agent->do(CommandProfile::load('generate'), $rec['instruction']);

            expect($outcome->proposals)->toBe([]);
            expect($outcome->prose)->toContain('ObjectBehavior');
        };
    };

    it('rejects a spec with a ->shouldReturn matcher (E5)', $reject('generate-spec-objectbehavior'));

    it('rejects a spec that calls a method on the subject $this (E6)', $reject('generate-spec-this-subject'));

});
