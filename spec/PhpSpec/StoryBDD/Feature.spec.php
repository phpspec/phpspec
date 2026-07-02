<?php

use PhpSpec\StoryBDD\Feature;
use PhpSpec\StoryBDD\FeatureNode;
use PhpSpec\StoryBDD\ScenarioNode;
use PhpSpec\StoryBDD\ScenarioOutlineNode;
use PhpSpec\StoryBDD\StepNode;
use PhpSpec\StoryBDD\BackgroundNode;
use PhpSpec\StoryBDD\StepRegistry;
use PhpSpec\StoryBDD\HookRegistry;
use PhpSpec\StoryBDD\DataTable;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;

describe(Feature::class, function () {

    it("runs a passing scenario", function () {
        $registry = new StepRegistry();
        $registry->addStep("a precondition", function () {
            $this->value = 42;
        });
        $registry->addStep("I check the value", function () {});
        $registry->addStep("it should be {int}", function ($n) {
            expect((int) $n)->toBe($this->value);
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'Test',
            '',
            null,
            [new ScenarioNode('Passing', [
                new StepNode('Given', 'a precondition'),
                new StepNode('When', 'I check the value'),
                new StepNode('Then', 'it should be 42'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        expect($result)->toBeAnInstanceOf(FeatureResult::class);
        expect($result->getTitle())->toBe('Test');

        $scenarios = $result->getResults();
        expect($scenarios)->toHaveCount(1);
        expect($scenarios[0])->toBeAnInstanceOf(ScenarioResult::class);

        $steps = $scenarios[0]->getResults();
        expect($steps)->toHaveCount(3);
        expect($steps[0]->isPassed())->toBeTrue();
        expect($steps[1]->isPassed())->toBeTrue();
        expect($steps[2]->isPassed())->toBeTrue();
    });

    it("marks undefined steps", function () {
        $feature = new Feature('test.feature', new FeatureNode(
            'Undefined',
            '',
            null,
            [new ScenarioNode('Missing', [
                new StepNode('Given', 'no such step'),
            ])]
        ), new StepRegistry(), new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isUndefined())->toBeTrue();
    });

    it("marks pending steps", function () {
        $registry = new StepRegistry();
        $registry->addStep("a pending step", function () {
            pending();
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'Pending',
            '',
            null,
            [new ScenarioNode('Pending', [
                new StepNode('Given', 'a pending step'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPending())->toBeTrue();
    });

    it("marks failed steps and skips remaining", function () {
        $registry = new StepRegistry();
        $registry->addStep("a failing step", function () {
            throw new \RuntimeException("boom");
        });
        $registry->addStep("a following step", function () {});

        $feature = new Feature('test.feature', new FeatureNode(
            'Failing',
            '',
            null,
            [new ScenarioNode('Fails', [
                new StepNode('Given', 'a failing step'),
                new StepNode('Then', 'a following step'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isFailure())->toBeTrue();
        expect($steps[0]->getError()->getMessage())->toBe("boom");
        expect($steps[1]->isSkipped())->toBeTrue();
    });

    it("shares world across steps in a scenario", function () {
        $registry = new StepRegistry();
        $registry->addStep("I set name to {string}", function ($name) {
            $this->name = $name;
        });
        $registry->addStep("name should be {string}", function ($expected) {
            expect($this->name)->toBe($expected);
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'World',
            '',
            null,
            [new ScenarioNode('Shared state', [
                new StepNode('Given', 'I set name to "Alice"'),
                new StepNode('Then', 'name should be "Alice"'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
        expect($steps[1]->isPassed())->toBeTrue();
    });

    it("runs background steps before each scenario", function () {
        $registry = new StepRegistry();
        $registry->addStep("a common setup", function () {
            $this->setup = true;
        });
        $registry->addStep("setup is done", function () {
            expect($this->setup)->toBeTrue();
        });

        $background = new BackgroundNode([
            new StepNode('Given', 'a common setup'),
        ]);

        $feature = new Feature('test.feature', new FeatureNode(
            'Background',
            '',
            $background,
            [new ScenarioNode('Uses background', [
                new StepNode('Then', 'setup is done'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
        expect($steps[1]->isPassed())->toBeTrue();
    });

    it("runs beforeScenario hooks", function () {
        $registry = new StepRegistry();
        $registry->addStep("the hook ran", function () {
            expect($this->hookRan)->toBeTrue();
        });

        $hooks = new HookRegistry();
        $hooks->addBeforeScenario(function () {
            $this->hookRan = true;
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'Hooks',
            '',
            null,
            [new ScenarioNode('With hook', [
                new StepNode('Then', 'the hook ran'),
            ])]
        ), $registry, $hooks);

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
    });

    it("runs multiple scenarios independently", function () {
        $registry = new StepRegistry();
        $registry->addStep("I set value to {int}", function ($n) {
            $this->value = (int) $n;
        });
        $registry->addStep("value is {int}", function ($n) {
            expect($this->value)->toBe((int) $n);
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'Multi',
            '',
            null,
            [
                new ScenarioNode('First', [
                    new StepNode('Given', 'I set value to 1'),
                    new StepNode('Then', 'value is 1'),
                ]),
                new ScenarioNode('Second', [
                    new StepNode('Given', 'I set value to 2'),
                    new StepNode('Then', 'value is 2'),
                ]),
            ]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        expect($result->getResults())->toHaveCount(2);
        expect($result->getResults()[0]->getTitle())->toBe('First');
        expect($result->getResults()[1]->getTitle())->toBe('Second');
    });

    it("expands scenario outlines into concrete scenarios", function () {
        $registry = new StepRegistry();
        $registry->addStep("there are {int} cucumbers", function ($n) {
            $this->count = (int) $n;
        });
        $registry->addStep("I eat {int} cucumbers", function ($n) {
            $this->count -= (int) $n;
        });
        $registry->addStep("I should have {int} cucumbers", function ($n) {
            expect($this->count)->toBe((int) $n);
        });

        $outline = new ScenarioOutlineNode('Eating', [
            new StepNode('Given', 'there are <start> cucumbers'),
            new StepNode('When', 'I eat <eat> cucumbers'),
            new StepNode('Then', 'I should have <left> cucumbers'),
        ], [], new DataTable([
            ['start', 'eat', 'left'],
            ['12', '5', '7'],
            ['20', '5', '15'],
        ]));

        $feature = new Feature('test.feature', new FeatureNode(
            'Outlines',
            '',
            null,
            [$outline]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $scenarios = $result->getResults();
        expect($scenarios)->toHaveCount(2);
        expect($scenarios[0]->getTitle())->toContain('12');
        expect($scenarios[1]->getTitle())->toContain('20');

        foreach ($scenarios as $scenario) {
            foreach ($scenario->getResults() as $step) {
                expect($step->isPassed())->toBeTrue();
            }
        }
    });

    it("runs beforeStep hooks", function () {
        $registry = new StepRegistry();
        $registry->addStep("a step", function () {
            expect($this->stepPrepped)->toBeTrue();
        });

        $hooks = new HookRegistry();
        $hooks->addBeforeStep(function () {
            $this->stepPrepped = true;
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'StepHooks',
            '',
            null,
            [new ScenarioNode('With step hook', [
                new StepNode('Given', 'a step'),
            ])]
        ), $registry, $hooks);

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
    });

    it("runs afterScenario hooks after the steps, even when a step fails", function () {
        $log = [];
        $registry = new StepRegistry();
        $registry->addStep("a passing step", function () use (&$log) {
            $log[] = 'step';
        });
        $registry->addStep("a failing step", function () {
            throw new \RuntimeException("boom");
        });

        $hooks = new HookRegistry();
        $hooks->addAfterScenario(function () use (&$log) {
            $log[] = 'afterScenario';
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'ScenarioTeardown',
            '',
            null,
            [
                new ScenarioNode('Passes', [new StepNode('Given', 'a passing step')]),
                new ScenarioNode('Fails', [new StepNode('Given', 'a failing step')]),
            ]
        ), $registry, $hooks);

        $feature->run();
        expect($log)->toBe(['step', 'afterScenario', 'afterScenario']);
    });

    it("runs afterStep hooks after each executed step but not for cascade-skipped ones", function () {
        $log = [];
        $registry = new StepRegistry();
        $registry->addStep("a failing step", function () use (&$log) {
            $log[] = 'failing step';
            throw new \RuntimeException("boom");
        });
        $registry->addStep("a following step", function () use (&$log) {
            $log[] = 'following step';
        });

        $hooks = new HookRegistry();
        $hooks->addAfterStep(function () use (&$log) {
            $log[] = 'afterStep';
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'StepTeardown',
            '',
            null,
            [new ScenarioNode('Fails midway', [
                new StepNode('Given', 'a failing step'),
                new StepNode('Then', 'a following step'),
            ])]
        ), $registry, $hooks);

        $feature->run();
        expect($log)->toBe(['failing step', 'afterStep']);
    });

    it("runs afterFeature hooks once after all scenarios", function () {
        $log = [];
        $registry = new StepRegistry();
        $registry->addStep("a step", function () use (&$log) {
            $log[] = 'step';
        });

        $hooks = new HookRegistry();
        $hooks->addAfterFeature(function () use (&$log) {
            $log[] = 'afterFeature';
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'FeatureTeardown',
            '',
            null,
            [
                new ScenarioNode('First', [new StepNode('Given', 'a step')]),
                new ScenarioNode('Second', [new StepNode('Given', 'a step')]),
            ]
        ), $registry, $hooks);

        $feature->run();
        expect($log)->toBe(['step', 'step', 'afterFeature']);
    });

    it("skips remaining background steps after failure", function () {
        $registry = new StepRegistry();
        $registry->addStep("a failing background step", function () {
            throw new \RuntimeException("bg fail");
        });
        $registry->addStep("a second background step", function () {});
        $registry->addStep("a scenario step", function () {});

        $background = new BackgroundNode([
            new StepNode('Given', 'a failing background step'),
            new StepNode('And', 'a second background step'),
        ]);

        $feature = new Feature('test.feature', new FeatureNode(
            'BgFail',
            '',
            $background,
            [new ScenarioNode('After bg fail', [
                new StepNode('Then', 'a scenario step'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isFailure())->toBeTrue();
        expect($steps[1]->isSkipped())->toBeTrue();
        expect($steps[2]->isSkipped())->toBeTrue();
    });

    it("exposes the feature path", function () {
        $feature = new Feature('/path/to/test.feature', new FeatureNode(
            'Path',
            '',
            null,
            []
        ), new StepRegistry(), new HookRegistry());

        expect($feature->getPath())->toBe('/path/to/test.feature');
    });

    it("passes data table to step callback", function () {
        $registry = new StepRegistry();
        $receivedTable = null;
        $registry->addStep("the following users:", function (DataTable $table) use (&$receivedTable) {
            $receivedTable = $table;
        });

        $table = new DataTable([
            ['name', 'age'],
            ['Alice', '30'],
            ['Bob', '25'],
        ]);

        $feature = new Feature('test.feature', new FeatureNode(
            'Tables',
            '',
            null,
            [new ScenarioNode('With table', [
                new StepNode('Given', 'the following users:', $table),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
        expect($receivedTable)->toBeAnInstanceOf(DataTable::class);
        expect($receivedTable)->toHaveCount(2);
        expect($receivedTable[0]['name'])->toBe('Alice');
    });

    it("passes doc string to step callback", function () {
        $registry = new StepRegistry();
        $receivedDoc = null;
        $registry->addStep("the following payload:", function (string $doc) use (&$receivedDoc) {
            $receivedDoc = $doc;
        });

        $feature = new Feature('test.feature', new FeatureNode(
            'DocStrings',
            '',
            null,
            [new ScenarioNode('With doc', [
                new StepNode('Given', 'the following payload:', null, '{"name": "Alice"}'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
        expect($receivedDoc)->toBe('{"name": "Alice"}');
    });

    it("passes both doc string and data table to step callback", function () {
        $registry = new StepRegistry();
        $receivedDoc = null;
        $receivedTable = null;
        $registry->addStep("a step with both:", function (string $doc, DataTable $table) use (&$receivedDoc, &$receivedTable) {
            $receivedDoc = $doc;
            $receivedTable = $table;
        });

        $table = new DataTable([
            ['key'],
            ['val'],
        ]);

        $feature = new Feature('test.feature', new FeatureNode(
            'Both',
            '',
            null,
            [new ScenarioNode('Both', [
                new StepNode('Given', 'a step with both:', $table, 'some text'),
            ])]
        ), $registry, new HookRegistry());

        $result = $feature->run();
        $steps = $result->getResults()[0]->getResults();
        expect($steps[0]->isPassed())->toBeTrue();
        expect($receivedDoc)->toBe('some text');
        expect($receivedTable)->toBeAnInstanceOf(DataTable::class);
    });

});
