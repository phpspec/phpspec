<?php

use PhpSpec\CodeGeneration\StepGenerator;
use PhpSpec\Filesystem;

describe(StepGenerator::class, function () {

    let('written', fn() => new ArrayObject());
    let('filesystem', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturnUsing(function (string $path, string $content) {
            $this->written['content'] = $content;
        });

        return $fs;
    });

    it('generates given/when/then from the primary keywords', function () {
        (new StepGenerator($this->filesystem))->generate('/proj/features/x.feature', [
            ['keyword' => 'Given', 'text' => 'a precondition'],
            ['keyword' => 'When', 'text' => 'an action'],
            ['keyword' => 'Then', 'text' => 'an outcome'],
        ]);

        expect($this->written['content'])->toContain('given("a precondition"');
        expect($this->written['content'])->toContain('when("an action"');
        expect($this->written['content'])->toContain('then("an outcome"');
    });

    it('generates And and But as the keyword of the step they follow', function () {
        (new StepGenerator($this->filesystem))->generate('/proj/features/x.feature', [
            ['keyword' => 'Given', 'text' => 'a precondition'],
            ['keyword' => 'And', 'text' => 'another precondition'],
            ['keyword' => 'When', 'text' => 'an action'],
            ['keyword' => 'But', 'text' => 'not another action'],
            ['keyword' => 'Then', 'text' => 'an outcome'],
            ['keyword' => 'And', 'text' => 'another outcome'],
        ]);

        $content = $this->written['content'];
        expect($content)->toContain('given("another precondition"');
        expect($content)->toContain('when("not another action"');
        expect($content)->toContain('then("another outcome"');
        expect($content)->not()->toContain('given("not another action"');
        expect($content)->not()->toContain('given("another outcome"');
    });

    it('defaults a leading And with no preceding primary to given', function () {
        (new StepGenerator($this->filesystem))->generate('/proj/features/x.feature', [
            ['keyword' => 'And', 'text' => 'a stray step'],
        ]);

        expect($this->written['content'])->toContain('given("a stray step"');
    });

    it('parses the Given/When/Then lines out of a feature text', function () {
        $steps = StepGenerator::parseSteps(<<<'GHERKIN'
        Feature: Adding a task
          Scenario: Adding a task
            Given I have a todo list
            When I add the task "Buy milk"
            Then I should have 1 task on my list
            And the task "Buy milk" should be on my list
        GHERKIN);

        expect($steps)->toBe([
            ['keyword' => 'Given', 'text' => 'I have a todo list'],
            ['keyword' => 'When', 'text' => 'I add the task "Buy milk"'],
            ['keyword' => 'Then', 'text' => 'I should have 1 task on my list'],
            ['keyword' => 'And', 'text' => 'the task "Buy milk" should be on my list'],
        ]);
    });

    it('drafts a complete steps file from parsed steps without touching disk', function () {
        $content = (new StepGenerator($this->filesystem))->skeleton([
            ['keyword' => 'Given', 'text' => 'I have a todo list'],
            ['keyword' => 'When', 'text' => 'I add the task "Buy milk"'],
        ]);

        expect($content)->toContain('<?php');
        expect($content)->toContain('given("I have a todo list"');
        expect($content)->toContain('when("I add the task {string}", function (string $arg1)');
        expect($content)->toContain('pending();');
    });

    it('appends only the missing steps to an existing steps file', function () {
        $existing = "<?php\n\ngiven(\"I have a todo list\", function () {\n    pending();\n});\n";

        $content = (new StepGenerator($this->filesystem))->skeleton([
            ['keyword' => 'Given', 'text' => 'I have a todo list'],
            ['keyword' => 'Then', 'text' => 'I should have 1 task on my list'],
        ], $existing);

        expect(substr_count($content, 'I have a todo list'))->toBe(1); // kept, not duplicated
        expect($content)->toContain('then("I should have {int} task on my list"');
    });

    it('lays the steps file beside its feature, and back', function () {
        expect(StepGenerator::stepsPathFor('features/completing_a_task.feature'))->toBe('features/steps/completing_a_task.steps.php');
        expect(StepGenerator::featurePathFor('features/steps/completing_a_task.steps.php'))->toBe('features/completing_a_task.feature');
    });
});
