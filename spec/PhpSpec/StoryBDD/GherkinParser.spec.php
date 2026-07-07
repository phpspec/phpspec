<?php

use PhpSpec\StoryBDD\GherkinParser;
use PhpSpec\StoryBDD\FeatureNode;
use PhpSpec\StoryBDD\ScenarioNode;
use PhpSpec\StoryBDD\StepNode;
use PhpSpec\StoryBDD\BackgroundNode;
use PhpSpec\StoryBDD\DataTable;

describe(GherkinParser::class, function () {

    let("parser", fn() => new GherkinParser());

    it("parses a feature title", function () {
        $feature = $this->parser->parse("Feature: Greeting");
        expect($feature->title)->toBe("Greeting");
    });

    it("parses a feature description", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Greeting
          As a user
          I want to be greeted
          So that I feel welcome
        GHERKIN);
        expect($feature->description)->toContain("As a user");
        expect($feature->description)->toContain("I want to be greeted");
    });

    it("parses a scenario with steps", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Greeting
          Scenario: Say hello
            Given I have a greeting service
            When I greet "World"
            Then I should see "Hello, World!"
        GHERKIN);
        expect($feature->scenarios)->toHaveCount(1);
        expect($feature->scenarios[0]->title)->toBe("Say hello");
        expect($feature->scenarios[0]->steps)->toHaveCount(3);
    });

    it("parses step keywords correctly", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Steps
          Scenario: Keywords
            Given a precondition
            When an action
            Then a result
            And another result
            But not this
        GHERKIN);
        $steps = $feature->scenarios[0]->steps;
        expect($steps[0]->keyword)->toBe("Given");
        expect($steps[1]->keyword)->toBe("When");
        expect($steps[2]->keyword)->toBe("Then");
        expect($steps[3]->keyword)->toBe("And");
        expect($steps[4]->keyword)->toBe("But");
    });

    it("parses step text without keyword", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Text
          Scenario: Step text
            Given I have 5 cucumbers
        GHERKIN);
        expect($feature->scenarios[0]->steps[0]->text)->toBe("I have 5 cucumbers");
    });

    it("parses multiple scenarios", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Multi
          Scenario: First
            Given step one

          Scenario: Second
            Given step two
        GHERKIN);
        expect($feature->scenarios)->toHaveCount(2);
        expect($feature->scenarios[0]->title)->toBe("First");
        expect($feature->scenarios[1]->title)->toBe("Second");
    });

    it("merges background steps into each scenario via pickles", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: With background
          Background:
            Given a common precondition

          Scenario: Using background
            When I do something
            Then something happens
        GHERKIN);
        // Pickles merge background steps into scenarios; no separate BackgroundNode
        expect($feature->background)->toBeNull();
        expect($feature->scenarios)->toHaveCount(1);
        // The scenario should contain the background step followed by its own steps
        expect($feature->scenarios[0]->steps)->toHaveCount(3);
        expect($feature->scenarios[0]->steps[0]->text)->toBe("a common precondition");
        expect($feature->scenarios[0]->steps[1]->text)->toBe("I do something");
        expect($feature->scenarios[0]->steps[2]->text)->toBe("something happens");
    });

    it("ignores comment lines", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        # This is a comment
        Feature: Comments
          # Another comment
          Scenario: With comments
            Given a step
            # Inline comment
            Then another step
        GHERKIN);
        expect($feature->title)->toBe("Comments");
        expect($feature->scenarios[0]->steps)->toHaveCount(2);
    });

    it("returns null background when none defined", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: No background
          Scenario: Simple
            Given a step
        GHERKIN);
        expect($feature->background)->toBeNull();
    });

    it("returns empty scenarios for feature-only", function () {
        $feature = $this->parser->parse("Feature: Empty");
        expect($feature->scenarios)->toHaveCount(0);
    });

    it("parses feature tags", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        @smoke @regression
        Feature: Tagged
          Scenario: Simple
            Given a step
        GHERKIN);
        expect($feature->tags)->toBe(["smoke", "regression"]);
    });

    it("parses scenario tags", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Tags
          @wip
          Scenario: Tagged scenario
            Given a step
        GHERKIN);
        expect($feature->scenarios[0]->tags)->toBe(["wip"]);
    });

    it("parses feature and scenario tags independently", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        @feature-tag
        Feature: Both
          @scenario-tag
          Scenario: Tagged
            Given a step
        GHERKIN);
        expect($feature->tags)->toBe(["feature-tag"]);
        expect($feature->scenarios[0]->tags)->toBe(["scenario-tag"]);
    });

    it("parses a data table under a step", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Tables
          Scenario: With table
            Given the following users:
              | name  | age |
              | Alice | 30  |
              | Bob   | 25  |
        GHERKIN);
        $step = $feature->scenarios[0]->steps[0];
        expect($step->table)->toBeAnInstanceOf(DataTable::class);
        expect($step->table)->toHaveCount(2);
        expect($step->table[0]['name'])->toBe("Alice");
        expect($step->table[1]['age'])->toBe("25");
    });

    it("parses steps without tables as having null table", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: No table
          Scenario: Simple
            Given a step without table
        GHERKIN);
        expect($feature->scenarios[0]->steps[0]->table)->toBeNull();
    });

    it("expands a scenario outline into concrete scenarios", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Outlines
          Scenario Outline: Eating
            Given there are <start> cucumbers
            When I eat <eat> cucumbers
            Then I should have <left> cucumbers

            Examples:
              | start | eat | left |
              | 12    | 5   | 7    |
              | 20    | 5   | 15   |
        GHERKIN);
        // Pickles expand outlines into individual scenarios
        expect($feature->scenarios)->toHaveCount(2);
        expect($feature->scenarios[0])->toBeAnInstanceOf(ScenarioNode::class);
        expect($feature->scenarios[0]->steps)->toHaveCount(3);
        expect($feature->scenarios[0]->steps[0]->text)->toBe("there are 12 cucumbers");
        expect($feature->scenarios[1]->steps[0]->text)->toBe("there are 20 cucumbers");
    });

    it("parses a tagged scenario outline", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Tagged outline
          @slow
          Scenario Outline: Slow test
            Given a step with <value>

            Examples:
              | value |
              | 1     |
        GHERKIN);
        expect($feature->scenarios[0]->tags)->toBe(["slow"]);
        expect($feature->scenarios[0])->toBeAnInstanceOf(ScenarioNode::class);
    });

    it("parses a doc string under a step", function () {
        $feature = $this->parser->parse(<<<'GHERKIN'
        Feature: DocStrings
          Scenario: With doc string
            Given the following JSON payload:
              """
              {
                "name": "Alice",
                "role": "admin"
              }
              """
        GHERKIN);
        $step = $feature->scenarios[0]->steps[0];
        expect($step->docString)->toContain('"name": "Alice"');
        expect($step->docString)->toContain('"role": "admin"');
    });

    it("parses steps without doc strings as having null docString", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: No doc
          Scenario: Simple
            Given a step without doc string
        GHERKIN);
        expect($feature->scenarios[0]->steps[0]->docString)->toBeNull();
    });

    it("parses a doc string between two steps", function () {
        $feature = $this->parser->parse(<<<'GHERKIN'
        Feature: Mid-doc
          Scenario: Doc between steps
            Given the following text:
              """
              Hello World
              """
            Then something should happen
        GHERKIN);
        $steps = $feature->scenarios[0]->steps;
        expect($steps)->toHaveCount(2);
        expect($steps[0]->docString)->toContain("Hello World");
        expect($steps[1]->docString)->toBeNull();
    });

    it("preserves doc string content exactly", function () {
        $feature = $this->parser->parse(<<<'GHERKIN'
        Feature: Exact
          Scenario: Preserve content
            Given a payload:
              """
              line one
                indented line
              line three
              """
        GHERKIN);
        $doc = $feature->scenarios[0]->steps[0]->docString;
        expect($doc)->toContain("line one");
        expect($doc)->toContain("  indented line");
        expect($doc)->toContain("line three");
    });

    it("handles doc string with only empty lines", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Empty doc
          Scenario: Blank doc
            Given a step:
              """

              """
        GHERKIN);
        $doc = $feature->scenarios[0]->steps[0]->docString;
        expect($doc)->toBeOfType('string');
    });

    it("parses a table between two steps", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Mid-table
          Scenario: Table between steps
            Given the following users:
              | name  |
              | Alice |
            Then there should be 1 user
        GHERKIN);
        $steps = $feature->scenarios[0]->steps;
        expect($steps)->toHaveCount(2);
        expect($steps[0]->table)->toBeAnInstanceOf(DataTable::class);
        expect($steps[0]->table)->toHaveCount(1);
        expect($steps[1]->table)->toBeNull();
    });

    it("throws on invalid Gherkin", function () {
        expect(fn() => $this->parser->parse("Not valid Gherkin at all\nScenario Outline:\n| broken"))->toThrow(\RuntimeException::class);
    });

    it("returns empty feature for content with no Feature keyword", function () {
        $feature = $this->parser->parse("");
        expect($feature->title)->toBe("");
        expect($feature->scenarios)->toHaveCount(0);
    });

    it("handles Rule children transparently", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: With rules
          Rule: First rule
            Scenario: Rule scenario one
              Given a step in rule one

          Rule: Second rule
            Scenario: Rule scenario two
              Given a step in rule two
        GHERKIN);
        expect($feature->scenarios)->toHaveCount(2);
        expect($feature->scenarios[0]->title)->toBe("Rule scenario one");
        expect($feature->scenarios[1]->title)->toBe("Rule scenario two");
        expect($feature->scenarios[0]->steps[0]->text)->toBe("a step in rule one");
    });

    it("merges background within a Rule into its scenarios", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Rule with background
          Rule: A rule
            Background:
              Given a rule-level setup

            Scenario: In rule
              When I act
        GHERKIN);
        expect($feature->scenarios)->toHaveCount(1);
        expect($feature->scenarios[0]->steps)->toHaveCount(2);
        expect($feature->scenarios[0]->steps[0]->text)->toBe("a rule-level setup");
        expect($feature->scenarios[0]->steps[1]->text)->toBe("I act");
    });

    it("preserves doc strings on steps", function () {
        $gherkin = "Feature: Doc string\n  Scenario: Has doc string\n    Given a step with doc string:\n      \"\"\"\n      Hello World\n      \"\"\"";
        $feature = $this->parser->parse($gherkin);
        expect($feature->scenarios[0]->steps[0]->docString)->toBe("Hello World");
    });

    it("preserves data tables on steps via pickles", function () {
        $gherkin = "Feature: Table\n  Scenario: Has table\n    Given a table:\n      | name  | age |\n      | Alice | 30  |";
        $feature = $this->parser->parse($gherkin);
        expect($feature->scenarios[0]->steps[0]->table)->toBeAnInstanceOf(\PhpSpec\StoryBDD\DataTable::class);
    });

    it("resolves step keywords from AST", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Keywords
          Scenario: Steps
            Given a context
            When an action
            Then an outcome
        GHERKIN);
        expect($feature->scenarios[0]->steps[0]->keyword)->toBe("Given");
        expect($feature->scenarios[0]->steps[1]->keyword)->toBe("When");
        expect($feature->scenarios[0]->steps[2]->keyword)->toBe("Then");
    });

    it("filters feature tags from scenario tags", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        @feature-tag
        Feature: Tagged
          @scenario-tag
          Scenario: Tagged scenario
            Given a step
        GHERKIN);
        expect($feature->tags)->toBe(["feature-tag"]);
        expect($feature->scenarios[0]->tags)->toBe(["scenario-tag"]);
    });

    it("records the line number of each scenario", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Lines
          Scenario: First
            Given a step

          Scenario: Second
            Given a step
        GHERKIN);
        expect($feature->scenarios[0]->line)->toBe(2);
        expect($feature->scenarios[0]->exampleLine)->toBeNull();
        expect($feature->scenarios[1]->line)->toBe(5);
    });

    it("gives outline-expanded scenarios the outline line and their example row line", function () {
        $feature = $this->parser->parse(<<<GHERKIN
        Feature: Outline lines
          Scenario Outline: Adding
            Given I add <a>

            Examples:
              | a |
              | 1 |
              | 2 |
        GHERKIN);
        expect($feature->scenarios)->toHaveCount(2);
        expect($feature->scenarios[0]->line)->toBe(2);
        expect($feature->scenarios[1]->line)->toBe(2);
        expect($feature->scenarios[0]->exampleLine)->toBe(7);
        expect($feature->scenarios[1]->exampleLine)->toBe(8);
    });

});
