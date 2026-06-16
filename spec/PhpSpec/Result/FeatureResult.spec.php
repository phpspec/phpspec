<?php

use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;

describe(FeatureResult::class, function () {
    it('returns the feature title', function () {
        $result = new FeatureResult('My Feature', []);
        expect($result->getTitle())->toBe('My Feature');
    });

    it('returns the feature file path', function () {
        $result = new FeatureResult('My Feature', [], 'features/my.feature');
        expect($result->getPath())->toBe('features/my.feature');
    });

    it('returns child scenario results', function () {
        $scenario = new ScenarioResult('my scenario', []);
        $result = new FeatureResult('My Feature', [$scenario]);

        expect($result->getResults())->toBe([$scenario]);
    });
});
