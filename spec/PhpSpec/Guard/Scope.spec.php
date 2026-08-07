<?php

use PhpSpec\Guard\Delta;
use PhpSpec\Guard\Scope;

describe(Scope::class, function () {

    it('judges files under the guarded roots', function () {
        $scope = new Scope(['src']);

        expect($scope->admits('src/App/Basket.php'))->toBeTrue();
        expect($scope->admits('tools/build.php'))->toBeFalse();
    });

    // Specs and features are the statement of intent. Demanding that the
    // intent be covered by itself would invert the whole idea.
    it('never judges a spec or a feature, whatever the roots say', function () {
        $scope = new Scope(['src', 'spec', 'features']);

        expect($scope->admits('spec/App/Basket.spec.php'))->toBeFalse();
        expect($scope->admits('features/steps/checkout.steps.php'))->toBeFalse();
    });

    it('drops what the project has allowed', function () {
        $scope = new Scope(['src'], ['src/Migrations/**']);

        expect($scope->admits('src/Migrations/Version20260101.php'))->toBeFalse();
        expect($scope->admits('src/App/Basket.php'))->toBeTrue();
    });

    it('has no opinion about a file that is not PHP', function () {
        expect((new Scope(['src']))->admits('src/config.yml'))->toBeFalse();
    });

    it('reads a path the same however it was written', function () {
        $scope = new Scope(['./src/']);

        expect($scope->admits('./src/App/Basket.php'))->toBeTrue();
        expect($scope->admits('src/App/Basket.php'))->toBeTrue();
    });

    it('bounds a delta to what it judges', function () {
        $delta = Delta::of([
            'src/App/Basket.php' => [3, 4],
            'spec/App/Basket.spec.php' => [10],
            'src/Migrations/Version1.php' => [1],
        ]);

        $bound = (new Scope(['src'], ['src/Migrations/**']))->bound($delta);

        expect($bound->files())->toBe(['src/App/Basket.php']);
        expect($bound->lines('src/App/Basket.php'))->toBe([3, 4]);
    });

});
