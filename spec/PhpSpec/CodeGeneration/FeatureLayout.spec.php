<?php

use PhpSpec\CodeGeneration\FeatureLayout;

describe(FeatureLayout::class, function () {
    let('layout', fn() => new FeatureLayout());

    it('lays the steps file beside its feature', function () {
        expect($this->layout->stepsPathFor('features/completing_a_task.feature'))->toBe('features/steps/completing_a_task.steps.php');
    });

    it('finds the feature a steps file belongs to', function () {
        expect($this->layout->featurePathFor('features/steps/completing_a_task.steps.php'))->toBe('features/completing_a_task.feature');
    });
});
