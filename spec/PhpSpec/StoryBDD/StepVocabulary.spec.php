<?php

use PhpSpec\Filesystem;
use PhpSpec\StoryBDD\StepVocabulary;

describe(StepVocabulary::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->scandir())->toReturn([]);
    });

    it('parses definition titles out of steps content, whatever the keyword or quoting', function (Filesystem $fs) {
        $titles = (new StepVocabulary($fs))->titlesIn(
            "<?php\n\ngiven('I have a todo list', function () {});\n"
            . "when(\"I add a {string} task\", function (string \$t) {});\n"
            . "step_and('something else happens', function () {});\n",
        );

        expect($titles)->toBe(['I have a todo list', 'I add a {string} task', 'something else happens']);
    });

    it('maps every title to the steps file defining it under a features root', function (Filesystem $fs) {
        $root = '/project/features';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $root);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$root, $root . '/steps'], true));
        allow($fs->isFile())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.steps.php'));
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
            $root => ['steps', 'adding.feature'],
            $root . '/steps' => ['adding.steps.php'],
            default => [],
        });
        allow($fs->read())->toReturn("<?php\ngiven('I have a todo list', function () {});\n");

        $titles = (new StepVocabulary($fs))->definedTitles($root);

        expect($titles)->toBe(['I have a todo list' => '/project/features/steps/adding.steps.php']);
    });

    it('reads the titles a steps.php defines', function (Filesystem $fs) {
        $root = '/project/features';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $root);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$root, $root . '/steps'], true));
        allow($fs->isFile())->toReturn(true);
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
            $root => ['steps', 'adding.feature'],
            $root . '/steps' => ['steps.php', 'helpers.php'],
            default => [],
        });
        allow($fs->read())->toReturnUsing(fn(string $p): string => str_ends_with($p, '/steps.php')
            ? "<?php\ngiven('I have a todo list', function () {});\n"
            : "<?php\ngiven('a helper that is no step file', function () {});\n");

        $titles = (new StepVocabulary($fs))->definedTitles($root);

        expect($titles)->toBe(['I have a todo list' => '/project/features/steps/steps.php']);
    });

    context('across several steps roots', function () {
        let('vocabulary', function (Filesystem $fs) {
            $files = [
                '/project/features/steps/steps.php' => "<?php\ngiven('a user named {string}', function (string \$name) {});\nwhen('the user plays', function () {});\n",
                '/project/features/steps/web.steps.php' => "<?php\ngiven('I visit {string}', function (string \$url) {});\n",
                '/project/acceptance_steps/steps.php' => "<?php\nthen('the player wins', function () {});\n",
            ];
            $dirs = [
                '/project/features' => ['steps', 'game.feature'],
                '/project/features/steps' => ['steps.php', 'web.steps.php'],
                '/project/acceptance_steps' => ['steps.php'],
            ];
            allow($fs->isDir())->toReturnUsing(fn(string $p): bool => isset($dirs[$p]));
            allow($fs->exists())->toReturnUsing(fn(string $p): bool => isset($dirs[$p]) || isset($files[$p]));
            allow($fs->scandir())->toReturnUsing(fn(string $p): array => $dirs[$p] ?? []);
            allow($fs->read())->toReturnUsing(fn(string $p): string => $files[$p] ?? '');

            return new StepVocabulary($fs);
        });

        it('maps the titles of every root', function () {
            expect(array_keys($this->vocabulary->definedTitles('/project/features', '/project/acceptance_steps')))
                ->toBe(['a user named {string}', 'the user plays', 'I visit {string}', 'the player wins']);
        });

        it('tells two steps.php apart by path, rejecting a title the other one owns', function () {
            $message = $this->vocabulary->rejectionFor(
                "<?php\ngiven('the user plays', function () {});\n",
                'acceptance_steps/steps.php',
                '/project/features',
                '/project/acceptance_steps',
            );

            expect($message)->toContain('/project/features/steps/steps.php');
        });

        it('lets the target file redefine its own titles, named by path', function () {
            expect($this->vocabulary->rejectionFor(
                "<?php\ngiven('a user named {string}', function (string \$name) {});\n",
                'features/steps/steps.php',
                '/project/features',
                '/project/acceptance_steps',
            ))->toBeNull();
        });

        it('names the files whose definitions serve the given steps, each once', function () {
            expect($this->vocabulary->filesServing(
                ['a user named "Chuck Norris"', 'the user plays', 'the player wins', 'something nobody defines'],
                '/project/features',
                '/project/acceptance_steps',
            ))->toBe(['/project/features/steps/steps.php', '/project/acceptance_steps/steps.php']);
        });
    });

    it('rejects content defining the same title twice', function (Filesystem $fs) {
        $message = (new StepVocabulary($fs))->rejectionFor(
            "<?php\ngiven('I filter the list', function () {});\nwhen('I filter the list', function () {});\n",
            'features/steps/filtering.steps.php',
            '/project/features',
        );

        expect($message)->toContain('"I filter the list"');
        expect($message)->toContain('twice');
    });

    it('rejects content redefining a title another steps file owns, naming that file', function (Filesystem $fs) {
        $root = '/project/features';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $root);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$root, $root . '/steps'], true));
        allow($fs->isFile())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.steps.php'));
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
            $root => ['steps'],
            $root . '/steps' => ['adding.steps.php'],
            default => [],
        });
        allow($fs->read())->toReturn("<?php\ngiven('I have a todo list', function () {});\n");

        $message = (new StepVocabulary($fs))->rejectionFor(
            "<?php\ngiven('I have a todo list', function () {});\n",
            'features/steps/clearing.steps.php',
            $root,
        );

        expect($message)->toContain('"I have a todo list"');
        expect($message)->toContain('adding.steps.php');
        expect($message)->toContain('reuse');
    });

    it('allows a file to redefine its own titles: an edit replaces the file', function (Filesystem $fs) {
        $root = '/project/features';
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => $p === $root);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => in_array($p, [$root, $root . '/steps'], true));
        allow($fs->isFile())->toReturnUsing(fn(string $p): bool => str_ends_with($p, '.steps.php'));
        allow($fs->scandir())->toReturnUsing(fn(string $p): array => match ($p) {
            $root => ['steps'],
            $root . '/steps' => ['clearing.steps.php'],
            default => [],
        });
        allow($fs->read())->toReturn("<?php\ngiven('I clear the list', function () { pending(); });\n");

        $message = (new StepVocabulary($fs))->rejectionFor(
            "<?php\ngiven('I clear the list', function () { \$this->list->clear(); });\n",
            'features/steps/clearing.steps.php',
            $root,
        );

        expect($message)->toBeNull();
    });

});
