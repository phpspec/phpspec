<?php

use PhpSpec\LineTargetRegistry;

describe(LineTargetRegistry::class, function () {

    afterEach(function () {
        LineTargetRegistry::reset();
    });

    it('has no target by default', function () {
        expect(LineTargetRegistry::currentTarget())->toBeNull();
    });

    it('exposes the target of the spec that begins running', function () {
        LineTargetRegistry::add('spec/App/Picky.spec.php', 6);

        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');
        expect(LineTargetRegistry::currentTarget())->toBe(6);

        LineTargetRegistry::beginSpec('spec/App/Other.spec.php');
        expect(LineTargetRegistry::currentTarget())->toBeNull();
    });

    it('ignores a leading ./ on either side', function () {
        LineTargetRegistry::add('./spec/App/Picky.spec.php', 6);

        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');

        expect(LineTargetRegistry::currentTarget())->toBe(6);
    });

    it('clears targets on reset', function () {
        LineTargetRegistry::add('spec/App/Picky.spec.php', 6);
        LineTargetRegistry::beginSpec('spec/App/Picky.spec.php');

        LineTargetRegistry::reset();

        expect(LineTargetRegistry::currentTarget())->toBeNull();
    });
});
