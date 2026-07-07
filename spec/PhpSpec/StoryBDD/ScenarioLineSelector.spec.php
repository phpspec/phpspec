<?php

use PhpSpec\StoryBDD\ScenarioLineSelector;
use PhpSpec\StoryBDD\ScenarioNode;

describe(ScenarioLineSelector::class, function () {

    beforeEach(function () {
        $this->first = new ScenarioNode('First', [], [], 2);
        $this->second = new ScenarioNode('Second', [], [], 5);
        $this->rowOne = new ScenarioNode('Adding', [], [], 8, 12);
        $this->rowTwo = new ScenarioNode('Adding', [], [], 8, 13);
        $this->scenarios = [$this->first, $this->second, $this->rowOne, $this->rowTwo];
    });

    it('selects the scenario whose keyword is on the given line', function () {
        expect(ScenarioLineSelector::select($this->scenarios, 5))->toBe([$this->second]);
    });

    it('selects the nearest scenario above a line inside its body', function () {
        expect(ScenarioLineSelector::select($this->scenarios, 3))->toBe([$this->first]);
    });

    it('selects every expansion of an outline when targeting its keyword line', function () {
        expect(ScenarioLineSelector::select($this->scenarios, 8))->toBe([$this->rowOne, $this->rowTwo]);
    });

    it('selects a single outline expansion when targeting its examples row', function () {
        expect(ScenarioLineSelector::select($this->scenarios, 13))->toBe([$this->rowTwo]);
    });

    it('selects nothing when the line is before the first scenario', function () {
        expect(ScenarioLineSelector::select($this->scenarios, 1))->toBe([]);
    });
});
