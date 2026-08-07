<?php

use PhpSpec\Filesystem;
use PhpSpec\Guard\Artifact;
use PhpSpec\Guard\Coverage;

describe(Artifact::class, function () {

    beforeEach(function () {
        $this->source = '<?php class Basket {}';

        // A report and the file it describes, as they sit on disk together.
        $this->onDisk = function (array $sources) {
            return fn(string $path) => $path === 'cov.json'
                ? (string) json_encode(['sources' => $sources])
                : $this->source;
        };
    });

    it('says so when the report is not there', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);

        expect((new Artifact($fs))->read('cov.json'))->toBe('Coverage file not found: cov.json.');
    });

    it('says so when the file is not a coverage report', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturn('{"hello":"world"}');

        expect((new Artifact($fs))->read('cov.json'))
            ->toBe('cov.json is not a PhpSpec coverage report: run --coverage-json to make one.');
    });

    // "lines" holds what ran and "executable" what there was to run. Without
    // the second, a line nothing reached and a line that was never code look
    // the same, and guard would call a closing brace untested logic.
    it('reads what ran and what there was to run', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(($this->onDisk)([
            'src/Basket.php' => [
                'checksum' => md5($this->source),
                'lines' => [9 => ['Basket::totals']],
                'executable' => [9, 14, 15],
            ],
        ]));

        $coverage = (new Artifact($fs))->read('cov.json');

        expect($coverage)->toBeAnInstanceOf(Coverage::class);
        expect($coverage->covers('src/Basket.php', 9))->toBeTrue();
        expect($coverage->covers('src/Basket.php', 14))->toBeFalse();
        expect($coverage->isExecutable('src/Basket.php', 14))->toBeTrue();
        // Never code, so guard has nothing to say about it.
        expect($coverage->isExecutable('src/Basket.php', 13))->toBeFalse();
    });

    // Reports made before guard existed have no "executable" list; what ran is
    // the most that can be known from them.
    it('falls back to what ran when the report predates the executable list', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(($this->onDisk)([
            'src/Basket.php' => ['checksum' => md5($this->source), 'lines' => [9 => ['Basket::totals']]],
        ]));

        $coverage = (new Artifact($fs))->read('cov.json');

        expect($coverage->covers('src/Basket.php', 9))->toBeTrue();
        expect($coverage->knows('src/Basket.php'))->toBeTrue();
    });

    // A report is a photograph of a moment. Read against a tree that has moved
    // on, it says lines are covered that nothing has ever run, and CI passes a
    // change nobody tested. The checksum in the report is there to stop that.
    it('refuses a report the code has moved on from', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(($this->onDisk)([
            'src/Basket.php' => ['checksum' => md5('what it held then'), 'lines' => [9 => ['Basket::totals']]],
        ]));

        expect((new Artifact($fs))->read('cov.json'))->toBe(
            'cov.json describes code that has changed since it was written: src/Basket.php. Run the suite again with --coverage-json.',
        );
    });

    // The change being judged may be the deletion itself, so a file that is no
    // longer there says nothing about whether the report is stale.
    it('accepts a report describing a file that has since been deleted', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $path) => $path === 'cov.json');
        allow($fs->read())->toReturnUsing(($this->onDisk)([
            'src/Gone.php' => ['checksum' => md5('what it held then'), 'lines' => [3 => ['Gone::go']]],
        ]));

        expect((new Artifact($fs))->read('cov.json'))->toBeAnInstanceOf(Coverage::class);
    });

});
