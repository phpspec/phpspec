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

        it('serves the pair tool schemas, write tools carrying the shared intent parameter', function () {
            $names = ['describe', 'add_example', 'generate_feature', 'generate_steps', 'write_file', 'update_file', 'offer_change', 'ask_user', 'run_specs', 'inspect_symbol', 'read_file', 'list_files'];
            $profile = new CommandProfile(name: 'driver', body: '', tools: $names);

            $schemas = [];
            foreach ($this->registry->definitions($profile) as $tool) {
                $schemas[$tool->getName()] = $tool->getParameterSchema();
            }

            expect(array_keys($schemas))->toBe($names);
            foreach (['describe', 'add_example', 'generate_feature', 'generate_steps', 'write_file', 'update_file', 'offer_change'] as $write) {
                expect($schemas[$write]['properties']['intent']['type'] ?? null)->toBe('string');
            }
            expect(in_array('path', $schemas['run_specs']['required'] ?? [], true))->toBe(false);
            expect($schemas['inspect_symbol']['properties']['fqcn']['type'] ?? null)->toBe('string');
            expect($schemas['ask_user']['properties']['question']['type'] ?? null)->toBe('string');
        });

        it('binds a provided handler in place of the propose-only no-op', function () {
            $profile = new CommandProfile(name: 'driver', body: '', tools: ['run_specs']);

            $tools = $this->registry->definitions($profile, ['run_specs' => fn(array $arguments) => 'RAN ' . $arguments['path']]);

            expect($tools[0]->execute(['path' => 'spec/App']))->toBe('RAN spec/App');
        });

    });

    context('deterministic', function () {

        it('defers a new feature to the model: scenario content is imagination, not scaffolding', function () {
            $step = new Step(Phase::WriteFeature, 'features/user_adds_tasks.feature', null, 'you named it');

            expect($this->registry->deterministic($step, Grounding::empty(), $this->genProfile))->toBeNull();
        });

        it('stands in with the skeleton when a feature ask produced nothing usable', function () {
            $step = new Step(Phase::WriteFeature, null, 'user_adds_tasks', 'feature intent');

            $proposal = $this->registry->featureFallback($step);

            expect($proposal->path)->toBe('features/user_adds_tasks.feature');
            expect($proposal->new)->toContain('Scenario:');
        });

        it('has no feature fallback for other steps', function () {
            expect($this->registry->featureFallback(new Step(Phase::WriteCode, null, 'App\\X', 'code intent')))->toBeNull();
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
                : "<?php\n\ngiven(\"I have a todo list\", function () {\n    pending();\n});");
            $step = new Step(Phase::WriteSteps, null, 'features/adding_a_task.feature', 'steps are undefined');

            $proposals = $this->registry->deterministic($step, Grounding::empty(), $this->genProfile);

            expect($proposals[0]->isNew)->toBe(false);
            expect(substr_count($proposals[0]->new, 'I have a todo list'))->toBe(1);
            expect($proposals[0]->new)->toContain('then("I should have {int} task on my list"');
        });

        it('declines the steps short-circuit when every step is already defined', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, '.feature') || str_ends_with($path, '.steps.php'));
            allow($fs->read())->toReturnUsing(fn(string $path): string => str_ends_with($path, '.feature')
                ? "Feature: Adding\n  Scenario: Adding\n    Given I have a todo list\n"
                : "<?php\n\ngiven(\"I have a todo list\", function () {\n    pending();\n});");
            $step = new Step(Phase::WriteSteps, null, 'features/adding_a_task.feature', 'you asked for steps');

            expect($this->registry->deterministic($step, Grounding::empty(), $this->genProfile))->toBeNull();
        });

        it('declines to re-scaffold a feature file that already exists', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, 'features/adding.feature'));
            allow($fs->read())->toReturn("Feature: Adding\n  Scenario: Adding\n    Given something\n");
            $step = new Step(Phase::WriteFeature, 'features/adding.feature', null, 'you named it');

            expect($this->registry->deterministic($step, Grounding::empty(), $this->genProfile))->toBeNull();
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

        it('places a bare spec filename from the step under the spec path', function () {
            $step = new Step(Phase::WriteSpec, 'Coupon.spec.php', null, 'you named it');
            $call = new ToolCall('1', 'propose_edit', ['path' => 'Coupon.spec.php', 'content' => "<?php\ndescribe('Coupon', fn() => null);"]);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('spec/Coupon.spec.php');
        });

        it('places a bare source filename from the step under the source path', function () {
            $step = new Step(Phase::WriteCode, 'Coupon.php', null, 'you named it');
            $call = new ToolCall('1', 'propose_edit', ['path' => 'Coupon.php', 'content' => "<?php\nclass Coupon {}"]);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('src/Coupon.php');
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

        it('skips a write_steps call when every step is already defined', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, '.feature') || str_ends_with($path, '.steps.php'));
            allow($fs->read())->toReturnUsing(fn(string $path): string => str_ends_with($path, '.feature')
                ? "Feature: Adding\n  Scenario: Adding\n    Given I have a todo list\n"
                : "<?php\n\ngiven(\"I have a todo list\", function () {\n    pending();\n});");
            $step = new Step(Phase::WriteSteps, null, 'features/adding_a_task.feature', 'you asked for steps');
            $call = new ToolCall('1', 'write_steps', ['feature_path' => 'features/adding_a_task.feature']);

            expect($this->registry->fromCalls([$call], $step))->toHaveLength(0);
        });

        it('skips a write_feature call for a feature file that already exists', function (Filesystem $fs) {
            allow($fs->exists())->toReturnUsing(fn(string $path): bool => str_ends_with($path, 'features/adding.feature'));
            allow($fs->read())->toReturn("Feature: Adding\n  Scenario: Adding\n    Given something\n");
            $step = new Step(Phase::WriteFeature, 'features/adding.feature', null, 'you named it');
            $call = new ToolCall('1', 'write_feature', ['path' => 'features/adding.feature']);

            expect($this->registry->fromCalls([$call], $step))->toHaveLength(0);
        });

        it('turns a write_feature call into a skeleton at the step slug path', function () {
            $step = new Step(Phase::WriteFeature, null, 'user_adds_tasks', 'feature intent');
            $call = new ToolCall('1', 'write_feature', ['name' => 'whatever_the_model_said']);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('features/user_adds_tasks.feature');
            expect($proposals[0]->new)->toContain('Feature:');
        });

        it('writes the model Gherkin at the explicit step path', function () {
            $step = new Step(Phase::WriteFeature, 'features/user_adds_tasks.feature', null, 'you named it');
            $call = new ToolCall('1', 'write_feature', ['content' => "Feature: Adding tasks\n  Scenario: Adds a task\n    Given an empty list\n    When I add \"milk\"\n    Then the list holds it\n"]);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('features/user_adds_tasks.feature');
            expect($proposals[0]->new)->toContain('When I add "milk"');
            expect($proposals[0]->origin)->toBe('write_feature');
        });

        it('places a bare feature filename under the configured features path, never the project root', function () {
            $step = new Step(Phase::WriteFeature, 'completing_a_task.feature', null, 'you named it');
            $call = new ToolCall('1', 'write_feature', ['content' => "Feature: Completing\n  Scenario: Done\n    Given a task\n    When I complete it\n    Then it is done\n"]);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('features/completing_a_task.feature');
        });

        it('falls back to the skeleton when the model content is no Gherkin', function () {
            $step = new Step(Phase::WriteFeature, null, 'user_adds_tasks', 'feature intent');
            $call = new ToolCall('1', 'write_feature', ['content' => 'here is your feature, enjoy']);

            $proposals = $this->registry->fromCalls([$call], $step);

            expect($proposals[0]->path)->toBe('features/user_adds_tasks.feature');
            expect($proposals[0]->new)->toContain('Feature:');
            expect($proposals[0]->new)->toContain('Scenario:');
        });

        it('rejects a steps edit that redefines a title another steps file owns', function (Filesystem $fs) {
            $cwd = getcwd();
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $cwd . '/features');
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$cwd . '/features', $cwd . '/features/steps'], true));
            allow($fs->isFile())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.steps.php'));
            allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
                $cwd . '/features' => ['steps'],
                $cwd . '/features/steps' => ['adding.steps.php'],
                default => [],
            });
            allow($fs->read())->toReturn("<?php\ngiven('I have a todo list', function () {});\n");

            $call = new ToolCall('1', 'propose_edit', [
                'path' => 'features/steps/clearing.steps.php',
                'content' => "<?php\ngiven('I have a todo list', function () { pending(); });\n",
            ]);

            expect(fn() => $this->registry->fromCalls([$call], null))
                ->toThrow(RuntimeException::class);
        });

        it('lets a steps edit replace the titles of the file it targets', function (Filesystem $fs) {
            $cwd = getcwd();
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $cwd . '/features');
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$cwd . '/features', $cwd . '/features/steps'], true));
            allow($fs->isFile())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.steps.php'));
            allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
                $cwd . '/features' => ['steps'],
                $cwd . '/features/steps' => ['clearing.steps.php'],
                default => [],
            });
            allow($fs->read())->toReturn("<?php\nwhen('I clear the list', function () { pending(); });\n");

            $call = new ToolCall('1', 'propose_edit', [
                'path' => 'features/steps/clearing.steps.php',
                'content' => "<?php\nwhen('I clear the list', function () { \$this->list->clear(); });\n",
            ]);

            $proposals = $this->registry->fromCalls([$call], null);

            expect($proposals[0]->new)->toContain('clear()');
        });

        it('scaffolds no stub for a step another steps file already defines', function (Filesystem $fs) {
            $cwd = getcwd();
            $feature = $cwd . '/features/clearing.feature';
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $feature || $p === $cwd . '/features');
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$cwd . '/features', $cwd . '/features/steps'], true));
            allow($fs->isFile())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.steps.php'));
            allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
                $cwd . '/features' => ['steps'],
                $cwd . '/features/steps' => ['adding.steps.php'],
                default => [],
            });
            allow($fs->read())->toReturnUsing(fn(string $p): string => $p === $feature
                ? "Feature: Clearing\n  Scenario: Clears\n    Given I have a todo list\n    When I clear the list\n"
                : "<?php\ngiven('I have a todo list', function () {});\n");

            $step = new Step(Phase::WriteSteps, null, 'features/clearing.feature', 'steps are undefined');
            $proposals = $this->registry->deterministic($step, Grounding::empty(), $this->genProfile);

            expect($proposals[0]->new)->not()->toContain('I have a todo list');
            expect($proposals[0]->new)->toContain('I clear the list');
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
