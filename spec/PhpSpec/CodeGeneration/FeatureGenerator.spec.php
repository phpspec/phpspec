<?php

use PhpSpec\CodeGeneration\FeatureGenerator;

describe(FeatureGenerator::class, function () {

    it('produces valid Gherkin with a Feature and a Scenario', function () {
        $content = (new FeatureGenerator())->skeleton('User adds tasks');

        expect($content)->toContain('Feature: User adds tasks');
        expect($content)->toContain('Scenario:');
        expect($content)->toContain('Given ');
        expect($content)->toContain('Then ');
    });

    it('derives a human title from a feature file path', function () {
        expect(FeatureGenerator::titleFromPath('features/user_adds_tasks.feature'))->toBe('User adds tasks');
    });

});
