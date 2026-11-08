<?php

use PhpSpec\RunTargets;

describe(RunTargets::class, function () {
    $whole = ['lines' => [], 'ids' => []];

    it('reads each path a run is given, a file run in part by the lines and ids after it', function () use ($whole) {
        $targets = RunTargets::of('spec/A.spec.php:4, spec/A.spec.php@e0953166a73b,spec/B.spec.php,features/x.feature:5');

        expect($targets->each())->toBe([
            'spec/A.spec.php' => ['lines' => [4], 'ids' => ['e0953166a73b']],
            'spec/B.spec.php' => $whole,
            'features/x.feature' => ['lines' => [5], 'ids' => []],
        ]);
    });

    it('names the targets a run of one file alone is given: its selectors, or the file itself', function () {
        $targets = RunTargets::of('spec/A.spec.php:4,spec/A.spec.php@e0953166a73b,spec/B.spec.php,features');

        expect($targets->targetsFor('spec/A.spec.php'))->toBe(['spec/A.spec.php:4', 'spec/A.spec.php@e0953166a73b']);
        expect($targets->targetsFor('spec/B.spec.php'))->toBe(['spec/B.spec.php']);
        expect($targets->targetsFor('spec/App/Found.spec.php'))->toBe(['spec/App/Found.spec.php']);
    });

    it('runs a file whole when it is given whole as well, however the two are spelled', function () use ($whole) {
        expect(RunTargets::of('./spec/A.spec.php:4,spec/A.spec.php')->each())->toBe(['./spec/A.spec.php' => $whole]);
        expect(RunTargets::of('spec/A.spec.php,./spec/A.spec.php:4')->each())->toBe(['spec/A.spec.php' => $whole]);
    });

    it('leaves a path inside a directory given whole to the directory', function () use ($whole) {
        $targets = RunTargets::of('spec/Billing,spec/Billing/Money.spec.php:16,./spec/Billing/Tax.spec.php,spec/Bill/Pay.spec.php:3');

        expect($targets->each())->toBe([
            'spec/Billing' => $whole,
            'spec/Bill/Pay.spec.php' => ['lines' => [3], 'ids' => []],
        ]);
        expect(RunTargets::of('.,spec/A.spec.php:4')->each())->toBe(['.' => $whole]);
    });

    it('names the file each target names, a selector after any file left off', function () {
        expect(RunTargets::of('src/Foo.php:10,spec,spec/A.spec.php@e0953166a73b')->files())->toBe([
            'src/Foo.php:10' => 'src/Foo.php',
            'spec' => 'spec',
            'spec/A.spec.php@e0953166a73b' => 'spec/A.spec.php',
        ]);
    });

    it('tells whether it runs part of a file, and whether every target given does', function () {
        expect(RunTargets::of('spec/A.spec.php@e0953166a73b,features')->selectsWithin())->toBeTrue();
        expect(RunTargets::of('spec,features')->selectsWithin())->toBeFalse();
        expect(RunTargets::of('spec/A.spec.php:4,spec/B.spec.php@e0953166a73b')->onlySelects())->toBeTrue();
        expect(RunTargets::of('spec/A.spec.php:4,spec/B.spec.php')->onlySelects())->toBeFalse();
        expect(RunTargets::of('')->onlySelects())->toBeFalse();
    });
});
