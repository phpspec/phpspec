<?php

use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Request;
use PhpSpec\Ai\Agent\Step;
use PhpSpec\Ai\PromptLibrary;
use PhpSpec\Console\Command\Run\SuiteSummary;
use PhpSpec\Filesystem;

// The request is composed by CONVENTION, not templates: command body, then the
// shared TDD cycle, then the current step's instruction file. The capture log
// lists exactly which files were composed, so a wrong prompt is found by name.
describe(Request::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $path): string => match (true) {
            str_contains($path, 'tdd-cycle') => 'THE-CYCLE',
            str_contains($path, 'write-steps') => 'STEPS-GUIDE',
            default => '',
        });
    });

    let('profile', fn() => new CommandProfile(name: 'generate', body: 'THE-BODY'));

    it('composes system as body, then the shared cycle, then the step instruction', function (Filesystem $fs) {
        $step = new Step(Phase::WriteSteps, null, 'features/adding.feature', 'steps are undefined');

        $request = Request::compose($this->profile, $step, Grounding::empty(), 'the steps', new PromptLibrary(null, $fs));

        expect($request->system)->toMatch('~THE-BODY.*THE-CYCLE.*STEPS-GUIDE~s');
        expect($request->composedFrom)->toBe(['commands/generate', 'instructions/tdd-cycle', 'instructions/write-steps']);
    });

    it('names the current step and its because, so the model knows where we are', function (Filesystem $fs) {
        $step = new Step(Phase::WriteSteps, null, null, 'steps are undefined');

        $request = Request::compose($this->profile, $step, Grounding::empty(), 'the steps', new PromptLibrary(null, $fs));

        expect($request->system)->toContain('write-steps');
        expect($request->system)->toContain('steps are undefined');
    });

    it('composes without a step section when no step resolved', function (Filesystem $fs) {
        $request = Request::compose($this->profile, null, Grounding::empty(), 'help me', new PromptLibrary(null, $fs));

        expect($request->system)->toMatch('~THE-BODY.*THE-CYCLE~s');
        expect($request->system)->not()->toContain('STEPS-GUIDE');
        expect($request->system)->not()->toContain('Current step');
    });

    it('grounds the context with the tree and named files, the instruction last', function (Filesystem $fs) {
        $grounding = new Grounding(
            tree: "src/\n  TodoList.php",
            namedFiles: ['src/TodoList.php' => '<?php class TodoList {}'],
        );

        $request = Request::compose($this->profile, null, $grounding, 'the steps', new PromptLibrary(null, $fs));

        expect($request->context)->toMatch('~# Project files.*# src/TodoList\.php.*# Instruction\nthe steps~s');
        expect($request->context)->toContain('class TodoList');
    });

    it('keeps the context to just the instruction when the grounding is empty', function (Filesystem $fs) {
        $request = Request::compose($this->profile, null, Grounding::empty(), 'the steps', new PromptLibrary(null, $fs));

        expect($request->context)->toBe("# Instruction\nthe steps");
    });

    it('marks overridden prompt names in composedFrom so the capture shows whose words the model heard', function (Filesystem $project, Filesystem $fs) {
        allow($project->exists())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.phpspec/prompts/instructions/tdd-cycle.txt'));
        allow($project->read())->toReturn('OUR-CYCLE');
        allow($fs->exists())->toReturn(true);
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_contains($p, 'write-steps') ? 'STEPS-GUIDE' : '');
        $profile = new CommandProfile(name: 'generate', body: 'THE-BODY', origin: PhpSpec\Ai\Prompt::PROJECT);
        $step = new Step(Phase::WriteSteps, null, 'features/adding.feature', 'steps are undefined');

        $request = Request::compose($profile, $step, Grounding::empty(), 'the steps', new PromptLibrary($project, $fs));

        expect($request->system)->toContain('OUR-CYCLE');
        expect($request->composedFrom)->toBe(['commands/generate (project)', 'instructions/tdd-cycle (project)', 'instructions/write-steps']);
    });

    it('renders the journal grounding so polish already done is visible to the model', function (Filesystem $fs) {
        $grounding = new Grounding(polished: ['App\\TodoList', 'App\\TaskQueue']);

        $request = Request::compose($this->profile, null, $grounding, '', new PromptLibrary(null, $fs));

        expect($request->context)->toContain('# Refactoring journal');
        expect($request->context)->toContain('Already refactored and unchanged since: App\\TodoList, App\\TaskQueue.');
    });

    it('renders the suite state block ahead of everything when the grounding carries one', function (Filesystem $fs) {
        $suite = new SuiteSummary(
            'green',
            ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
            [],
            [],
            ['features' => 1, 'scenarios' => 1, 'steps' => 3, 'stepFailures' => 0, 'undefined' => 3],
            [['path' => 'features/adding.feature', 'status' => 'todo', 'undefined' => 3]],
        );
        $grounding = new Grounding(suite: $suite, recentFeature: 'features/adding.feature');

        $request = Request::compose($this->profile, null, $grounding, '', new PromptLibrary(null, $fs));

        expect($request->context)->toMatch('~# Suite state.*# Instruction~s');
        expect($request->context)->toContain('FEATURES: 1 features, 1 scenarios, 3 steps (0 failing, 3 undefined)');
        expect($request->context)->toContain('- todo: features/adding.feature');
        expect($request->context)->toContain('Last-touched feature: features/adding.feature');
    });

});
