<?php

use PhpSpec\Ai\Agent\CommandProfile;
use PhpSpec\Ai\Agent\Grounding;
use PhpSpec\Ai\Agent\Phase;
use PhpSpec\Ai\Agent\Step;
use PhpSpec\Ai\Agent\ToolRegistry;
use PhpSpec\Ai\ToolCall;
use PhpSpec\Configuration;
use PhpSpec\Filesystem;

// Tools are shared definitions: description from tools/<name>.txt, schema and
// deterministic generator in code, and every execution returns Proposals only.
// The registry also owns the deterministic short-circuit: when the step fully
// determines the artifact, the model is never consulted.
describe(ToolRegistry::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
        allow($fs->read())->toReturn('');
    });

    let('config', fn(Filesystem $fs) => new Configuration('.', $fs));
    let('registry', fn(Filesystem $fs) => new ToolRegistry($this->config, $fs));
    let('genProfile', fn() => new CommandProfile(name: 'generate', body: '', tools: ['write_feature', 'write_steps', 'propose_edit']));

    context('definitions', function () {

        it('builds the declared tools with their prompt-file descriptions', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(true);
            allow($fs->read())->toReturnUsing(fn(string $path): string => str_contains($path, 'write_steps') ? 'STEPS TOOL DESC' : 'OTHER');
            $profile = new CommandProfile(name: 'generate', body: '', tools: ['write_feature', 'write_steps']);

            $tools = $this->registry->definitions($profile);

            expect(array_map(fn($tool) => $tool->getName(), $tools))->toBe(['write_feature', 'write_steps']);
            expect($tools[1]->getDescription())->toBe('STEPS TOOL DESC');
        });

        it('rejects a tool name the registry does not know', function () {
            $profile = new CommandProfile(name: 'generate', body: '', tools: ['nonsense']);

            expect(fn() => $this->registry->definitions($profile))
                ->toThrow(RuntimeException::class, 'Unknown tool "nonsense" declared by command "generate".');
        });

    });

    context('deterministic', function () {

        it('resolves a write-feature step to a Gherkin skeleton at the explicit path', function () {
            $step = new Step(Phase::WriteFeature, 'features/user_adds_tasks.feature', null, 'you named it');

            $proposals = $this->registry->deterministic($step, Grounding::empty(), $this->genProfile);

            expect($proposals[0]->path)->toBe('features/user_adds_tasks.feature');
            expect($proposals[0]->new)->toContain('Feature:');
            expect($proposals[0]->new)->toContain('Scenario:');
            expect($proposals[0]->origin)->toBe('write_feature');
        });

        it('derives the feature path from the slug under the configured features path', function () {
            $step = new Step(Phase::WriteFeature, null, 'user_adds_tasks', 'feature intent');

            $proposals = $this->registry->deterministic($step, Grounding::empty(), $this->genProfile);

            expect($proposals[0]->path)->toBe('features/user_adds_tasks.feature');
        });

        it('parses the subject feature and drafts its steps file deterministically', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, 'features/adding_a_task.feature'));
            allow($fs->read())->toReturn("Feature: Adding\n  Scenario: Adding\n    Given I have a todo list\n    When I add the task \"Buy milk\"\n");
            $step = new Step(Phase::WriteSteps, null, 'features/adding_a_task.feature', 'steps are undefined');

            $proposals = $this->registry->deterministic($step, Grounding::empty(), $this->genProfile);

            expect($proposals[0]->path)->toBe('features/steps/adding_a_task.steps.php');
            expect($proposals[0]->new)->toContain('given("I have a todo list"');
            expect($proposals[0]->new)->toContain('when("I add the task {string}"');
            expect($proposals[0]->isNew)->toBe(true);
        });

        it('appends only the missing steps when the steps file already exists', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, '.feature') || str_ends_with($path, '.steps.php'));
            allow($fs->read())->toReturnUsing(fn(string $path): string => str_ends_with($path, '.feature')
                ? "Feature: Adding\n  Scenario: Adding\n    Given I have a todo list\n    Then I should have 1 task on my list\n"
                : "<?php\n\ngiven(\"I have a todo list\", function () {\n    pending();\n});\n");
            $step = new Step(Phase::WriteSteps, null, 'features/adding_a_task.feature', 'steps are undefined');

            $proposals = $this->registry->deterministic($step, Grounding::empty(), $this->genProfile);

            expect($proposals[0]->isNew)->toBe(false);
            expect(substr_count($proposals[0]->new, 'I have a todo list'))->toBe(1);
            expect($proposals[0]->new)->toContain('then("I should have {int} task on my list"');
        });

        it('refuses steps for a feature that does not exist, naming it', function () {
            $step = new Step(Phase::WriteSteps, null, 'features/missing.feature', 'steps asked for');

            expect(fn() => $this->registry->deterministic($step, Grounding::empty(), $this->genProfile))
                ->toThrow(RuntimeException::class, 'Feature file "features/missing.feature" was not found, so there are no steps to write.');
        });

        it('leaves write-spec, write-code, and unresolved steps to the model', function () {
            expect($this->registry->deterministic(new Step(Phase::WriteSpec, null, 'Coupon', 'spec intent'), Grounding::empty(), $this->genProfile))->toBeNull();
            expect($this->registry->deterministic(new Step(Phase::WriteCode, null, 'Coupon', 'code intent'), Grounding::empty(), $this->genProfile))->toBeNull();
            expect($this->registry->deterministic(new Step(Phase::WriteSteps, null, null, 'no feature found'), Grounding::empty(), $this->genProfile))->toBeNull();
            expect($this->registry->deterministic(null, Grounding::empty(), $this->genProfile))->toBeNull();
        });

        it('never short-circuits a tool the command did not declare', function () {
            // `next` derives a write-steps step from a todo feature, but it only
            // advises: with no write_steps in its manifest, nothing is proposed.
            $next = new CommandProfile(name: 'next', body: '', tools: ['suggest_next']);
            $step = new Step(Phase::WriteSteps, null, 'features/adding.feature', 'derived from the suite');

            expect($this->registry->deterministic($step, Grounding::empty(), $next))->toBeNull();
        });

    });

    context('reportFrom', function () {

        it('collects a suggest_next call as the report payload', function () {
            $calls = [new ToolCall('1', 'suggest_next', ['type' => 'spec', 'target' => 'App\\Coupon', 'reason' => 'Nothing persists coupons yet.'])];

            expect($this->registry->reportFrom($calls))->toBe(['type' => 'spec', 'target' => 'App\\Coupon', 'reason' => 'Nothing persists coupons yet.']);
        });

        it('reports nothing when no report tool was called', function () {
            expect($this->registry->reportFrom([new ToolCall('1', 'propose_edit', ['path' => 'x', 'content' => 'y'])]))->toBe([]);
        });

    });

    context('fromCalls', function () {

        it('turns a propose_edit call into a proposal, the step-derived path winning', function () {
            $step = new Step(Phase::WriteSpec, null, 'Coupon', 'spec intent');
            $call = new ToolCall('1', 'propose_edit', ['path' => 'spec/App/Whatever.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"]);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('spec/Coupon.spec.php');
            expect($proposals[0]->new)->toContain('Coupon');
        });

        it('rejects a propose_edit spec written in ObjectBehavior syntax', function () {
            $step = new Step(Phase::WriteSpec, null, 'Calc', 'spec intent');
            $call = new ToolCall('1', 'propose_edit', ['path' => 'spec/Calc.spec.php', 'content' => "<?php\ndescribe('Calc', function () { it('adds', function () { \$this->add(2)->shouldReturn(2); }); });"]);

            expect(fn() => $this->registry->fromCalls([$call], $step))->toThrow(RuntimeException::class);
        });

        it('keeps the model path for a propose_edit with no step to derive from', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, 'src/App/Calc.php'));
            allow($fs->read())->toReturn("<?php\n// old");
            $call = new ToolCall('1', 'propose_edit', ['path' => 'src/App/Calc.php', 'content' => "<?php\n// new"]);

            $proposals = $this->registry->fromCalls([$call], null);

            expect($proposals[0]->path)->toBe('src/App/Calc.php');
            expect($proposals[0]->old)->toBe("<?php\n// old");
            expect($proposals[0]->isNew)->toBe(false);
        });

        it('turns a write_feature call into a skeleton at the step slug path', function () {
            $step = new Step(Phase::WriteFeature, null, 'user_adds_tasks', 'feature intent');
            $call = new ToolCall('1', 'write_feature', ['name' => 'whatever_the_model_said']);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('features/user_adds_tasks.feature');
            expect($proposals[0]->new)->toContain('Feature:');
        });

        it('serves a write_steps call from the argument feature path when the step has none', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, 'features/adding.feature'));
            allow($fs->read())->toReturn("Feature: Adding\n  Scenario: A\n    Given a list\n");

            $proposals = $this->registry->fromCalls([new ToolCall('1', 'write_steps', ['feature_path' => 'features/adding.feature'])], null);

            expect($proposals[0]->path)->toBe('features/steps/adding.steps.php');
            expect($proposals[0]->new)->toContain('given("a list"');
        });

    });

});
