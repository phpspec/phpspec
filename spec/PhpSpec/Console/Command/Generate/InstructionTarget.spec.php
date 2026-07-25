<?php

use PhpSpec\Console\Command\Generate\InstructionTarget;

describe(InstructionTarget::class, function () {

    it('extracts a feature target from an explicit .feature path', function () {
        expect(InstructionTarget::parse('a simple scenario in features/user_adds_tasks.feature'))
            ->toBe(['path' => 'features/user_adds_tasks.feature', 'type' => 'feature']);
    });

    it('extracts a spec target from an explicit .spec.php path', function () {
        expect(InstructionTarget::parse('add an example to spec/App/Calculator.spec.php'))
            ->toBe(['path' => 'spec/App/Calculator.spec.php', 'type' => 'spec']);
    });

    it('extracts a code target from an explicit src .php path', function () {
        expect(InstructionTarget::parse('implement the add method in src/App/TodoList.php'))
            ->toBe(['path' => 'src/App/TodoList.php', 'type' => 'code']);
    });

    it('infers a feature intent (with a subject slug) from feature-intent wording without a path', function () {
        $target = InstructionTarget::parse('a feature describing a user adding a task to a todo list');

        expect($target['type'])->toBe('feature');
        expect($target['slug'])->toContain('user');
    });

    it('infers a spec intent and the class from spec-intent wording', function () {
        expect(InstructionTarget::parse('a spec for a Coupon that reduces a total'))
            ->toBe(['type' => 'spec', 'class' => 'Coupon']);
    });

    it('infers a code intent and the class from implementation-intent wording', function () {
        expect(InstructionTarget::parse('implement the add method on the TodoList class'))
            ->toBe(['type' => 'code', 'class' => 'TodoList']);
    });

    it('returns null when the instruction names neither a path nor a known intent', function () {
        expect(InstructionTarget::parse('make the calculator add two numbers'))->toBeNull();
    });

});
