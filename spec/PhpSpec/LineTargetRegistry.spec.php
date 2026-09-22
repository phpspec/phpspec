<?php

use PhpSpec\LineTargetRegistry;

describe(LineTargetRegistry::class, function () {

    afterEach(function () {
        LineTargetRegistry::reset();
    });

    it('has no targets by default', function () {
        expect(LineTargetRegistry::currentTargets())->toBe([]);
    });

    it('exposes the targets of the spec that begins running', function () {
        LineTargetRegistry::add('spec/App/Picky.spec.php', 6);

        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');
        expect(LineTargetRegistry::currentTargets())->toBe([6]);

        LineTargetRegistry::beginSpec('spec/App/Other.spec.php');
        expect(LineTargetRegistry::currentTargets())->toBe([]);
    });

    it('keeps every line targeted on one spec', function () {
        LineTargetRegistry::add('spec/App/Picky.spec.php', 6);
        LineTargetRegistry::add('spec/App/Picky.spec.php', 9, 12);

        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');

        expect(LineTargetRegistry::currentTargets())->toBe([6, 9, 12]);
    });

    it('ignores a leading ./ on either side', function () {
        LineTargetRegistry::add('./spec/App/Picky.spec.php', 6);

        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');

        expect(LineTargetRegistry::currentTargets())->toBe([6]);
    });

    it('clears targets on reset', function () {
        LineTargetRegistry::add('spec/App/Picky.spec.php', 6);
        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');

        LineTargetRegistry::reset();

        expect(LineTargetRegistry::currentTargets())->toBe([]);
    });
});
