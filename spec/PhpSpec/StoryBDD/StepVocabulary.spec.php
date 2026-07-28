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
