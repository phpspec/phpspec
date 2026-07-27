<?php

use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Step;
use PhpSpec\Console\Command\Run\SuiteSummary;

// The keystone of the agent pipeline: ONE deterministic function answers "what
// are we doing?". The user's words always win; otherwise the suite state IS the
// cycle position. Every step carries its "because" so a wrong step is debugged
// by reading a sentence, not re-deriving the table.
describe(Step::class, function () {

    // --- intent: the user's words win -----------------------------------

    it('maps an explicit .feature path to write-feature', function () {
        $step = Step::resolve('a simple scenario in features/user_adds_tasks.feature', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteFeature);
        expect($step->path)->toBe('features/user_adds_tasks.feature');
        expect($step->because)->toContain('features/user_adds_tasks.feature');
    });

    it('maps an explicit .spec.php path to write-spec', function () {
        $step = Step::resolve('add an example to spec/App/Calculator.spec.php', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteSpec);
        expect($step->path)->toBe('spec/App/Calculator.spec.php');
    });

    it('maps an explicit .steps.php path to write-steps', function () {
        $step = Step::resolve('grow features/steps/adding.steps.php', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteSteps);
        expect($step->path)->toBe('features/steps/adding.steps.php');
    });

    it('maps an explicit source .php path to write-code', function () {
        $step = Step::resolve('implement the add method in src/App/TodoList.php', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteCode);
        expect($step->path)->toBe('src/App/TodoList.php');
    });

    it('routes a steps request naming a feature to write-steps for that feature', function () {
        $step = Step::resolve('generate the steps for features/adding_a_task.feature', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteSteps);
        expect($step->subject)->toBe('features/adding_a_task.feature');
    });

    it('routes a bare steps request to write-steps for the last-touched feature', function () {
        $grounding = new Grounding(recentFeature: 'features/adding_a_task.feature');

        $step = Step::resolve('the steps', $grounding);

        expect($step->phase)->toBe(Phase::WriteSteps);
        expect($step->subject)->toBe('features/adding_a_task.feature');
        expect($step->because)->toContain('last-touched');
    });

    it('still resolves write-steps when no feature exists to attach to', function () {
        $step = Step::resolve('generate the steps', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteSteps);
        expect($step->subject)->toBeNull();
    });

    it('infers write-feature (with a subject slug) from feature wording', function () {
        $step = Step::resolve('a feature describing a user adding a task to a todo list', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteFeature);
        expect($step->subject)->toContain('user');
    });

    it('prefers the named class with spec wording over loose feature wording in the tail', function () {
        $step = Step::resolve('add a spec example for App\\TodoList: drive the pending feature', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteSpec);
        expect($step->subject)->toBe('App\\TodoList');
    });

    it('infers write-spec and the class from spec wording', function () {
        $step = Step::resolve('a spec for a Coupon that reduces a total', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteSpec);
        expect($step->subject)->toBe('Coupon');
    });

    it('infers write-code and the class from implementation wording', function () {
        $step = Step::resolve('implement the add method on the TodoList class', Grounding::empty());

        expect($step->phase)->toBe(Phase::WriteCode);
        expect($step->subject)->toBe('TodoList');
    });

    // --- derived: the suite state is the cycle position -----------------

    it('resolves nothing when there is no intent and no suite state', function () {
        expect(Step::resolve('make the calculator add two numbers', Grounding::empty()))->toBeNull();
    });

    it('derives write-steps from a feature with undefined steps', function () {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
            [['path' => 'features/adding_a_task.feature', 'status' => 'todo', 'undefined' => 3]],
        );

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->phase)->toBe(Phase::WriteSteps);
        expect($step->subject)->toBe('features/adding_a_task.feature');
        expect($step->because)->toContain('undefined');
    });

    it('derives write-spec from a red feature with no failing unit example (descend)', function () {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 1, 'undefined' => 0],
            [['path' => 'features/adding_a_task.feature', 'status' => 'red', 'undefined' => 0]],
        );

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->phase)->toBe(Phase::WriteSpec);
        expect($step->because)->toContain('features/adding_a_task.feature');
    });

    it('derives write-code from a failing unit example (make it green)', function () {
        $suite = new SuiteSummary(
            'red',
            ['examples' => 3, 'passes' => 2, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'App\\TodoList', 'example' => 'it adds a task', 'error' => 'boom']],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 0],
            [['path' => 'features/adding_a_task.feature', 'status' => 'green', 'undefined' => 0]],
        );

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->phase)->toBe(Phase::WriteCode);
        expect($step->subject)->toBe('App\\TodoList');
        expect($step->because)->toContain('it adds a task');
    });

    it('names a degenerate failure once when its subject and example coincide', function () {
        $suite = new SuiteSummary(
            'red',
            ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
            [['subject' => 'deleting_a_task', 'example' => 'deleting_a_task', 'error' => 'boom']],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 2, 'stepFailures' => 0, 'undefined' => 0],
            [['path' => 'features/deleting_a_task.feature', 'status' => 'green', 'undefined' => 0]],
        );

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->because)->toBe('the suite is red: deleting_a_task');
    });

    it('derives write-steps for a feature whose steps are pending: finish the working story', function () {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 5, 'passes' => 5, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 3, 'scenarios' => 4, 'steps' => 20, 'stepFailures' => 0, 'undefined' => 0],
            [
                ['path' => 'features/adding.feature', 'status' => 'green', 'undefined' => 0],
                ['path' => 'features/clearing_completed_tasks.feature', 'status' => 'pending', 'undefined' => 0],
            ],
        );

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->phase)->toBe(Phase::WriteSteps);
        expect($step->subject)->toBe('features/clearing_completed_tasks.feature');
        expect($step->because)->toContain('pending');
    });

    it('derives refactor when features are green', function () {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 3, 'passes' => 3, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 0],
            [['path' => 'features/adding_a_task.feature', 'status' => 'green', 'undefined' => 0]],
        );

        expect(Step::resolve('', new Grounding(suite: $suite))->phase)->toBe(Phase::Refactor);
    });

    it('derives write-spec for the first class on an empty spec-only project', function () {
        $suite = new SuiteSummary('green');

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->phase)->toBe(Phase::WriteSpec);
        expect($step->subject)->toBeNull();
    });

    it('derives write-spec for the nearest pending gap on a green spec-only suite', function () {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 3, 'passes' => 2, 'failures' => 0, 'errors' => 0, 'pending' => 1],
            [],
            [['subject' => 'App\\Coupon', 'example' => 'it reduces a total', 'error' => '']],
        );

        $step = Step::resolve('', new Grounding(suite: $suite));

        expect($step->phase)->toBe(Phase::WriteSpec);
        expect($step->subject)->toBe('App\\Coupon');
    });

    it('derives refactor on a green, gapless spec-only suite', function () {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 3, 'passes' => 3, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        );

        expect(Step::resolve('', new Grounding(suite: $suite))->phase)->toBe(Phase::Refactor);
    });

    it('always explains itself: every resolved step carries a non-empty because', function () {
        $step = Step::resolve('a spec for a Coupon', Grounding::empty());

        expect($step->because)->not()->toBe('');
    });

});
