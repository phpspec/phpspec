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

    it('returns null when the instruction names no path', function () {
        expect(InstructionTarget::parse('a feature for a user adding a task'))->toBeNull();
    });

});
