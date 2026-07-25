<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Filesystem;

// E5 & E6 (evals, adversarial) — when the model returns a spec in phpspec-8
// ObjectBehavior syntax (a `->shouldReturn()` matcher, or a method called on the
// subject `$this`), the scaffold must never propose it. One-shot behaviour is to
// reject (return null) rather than write DSL that would fatal when phpspec loads
// it; a re-ask loop can retry later.
describe('E5/E6 generate: reject ObjectBehavior spec syntax', function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    $reject = function (string $case) {
        return function (Filesystem $fs) use ($case) {
            $rec = json_decode((string) file_get_contents(__DIR__ . '/recordings/' . $case . '.json'), true);
            $replay = fn(array $ai, string $context): ?string => $rec['response']['text'];

            $agent = new GenerateAgent(new Configuration('.', $fs), $fs, $replay);

            expect($agent->propose($rec['aiConfig'], $rec['instruction']))->toBeNull();
        };
    };

    it('rejects a spec with a ->shouldReturn matcher (E5)', $reject('generate-spec-objectbehavior'));

    it('rejects a spec that calls a method on the subject $this (E6)', $reject('generate-spec-this-subject'));

});
