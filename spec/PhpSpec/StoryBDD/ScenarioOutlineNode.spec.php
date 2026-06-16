<?php

use PhpSpec\StoryBDD\ScenarioOutlineNode;
use PhpSpec\StoryBDD\ScenarioNode;
use PhpSpec\StoryBDD\StepNode;

describe(ScenarioOutlineNode::class, function () {

    it("expands to a single scenario when no examples table is given", function () {
        $steps = [new StepNode('Given', 'a step')];
        $outline = new ScenarioOutlineNode('Outline', $steps);
        $expanded = $outline->expand();
        expect($expanded)->toHaveCount(1);
        expect($expanded[0])->toBeAnInstanceOf(ScenarioNode::class);
    });

});
