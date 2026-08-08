<?php

use PhpSpec\ProjectRoot;

describe(ProjectRoot::class, function () {

    it('strips itself from a path inside it', function () {
        $root = ProjectRoot::at('/app');

        expect($root->relative('/app/src/Basket.php'))->toBe('src/Basket.php');
    });

    it('leaves a path that is not inside it alone', function () {
        $root = ProjectRoot::at('/app');

        expect($root->relative('/elsewhere/src/Basket.php'))->toBe('/elsewhere/src/Basket.php');
    });

    it('reads a path the same however its separators are written', function () {
        $root = ProjectRoot::at('C:\\project');

        expect($root->relative('C:/project/src/Basket.php'))->toBe('src/Basket.php');
        expect($root->relative('C:\\project\\src\\Basket.php'))->toBe('src/Basket.php');
    });

    it('knows whether a path is inside it', function () {
        $root = ProjectRoot::at('/app/src');

        expect($root->holds('/app/src/Basket.php'))->toBeTrue();
        expect($root->holds('/app/spec/Basket.spec.php'))->toBeFalse();
    });

    // The reason this class exists. One call gives PHP the directory as it was
    // typed and another gives it as the filesystem resolves it, and on Windows
    // those are different spellings of one place ("RUNNER~1" against
    // "runneradmin"). A root that only knows one of them matches nothing, and
    // every path stays absolute: coverage of a file nobody can name, a guard
    // that reads every changed line as untested.
    it('answers to every spelling of itself the filesystem accepts', function () {
        $directory = sys_get_temp_dir() . '/phpspec_root_' . getmypid();
        @mkdir($directory . '/src', 0777, true);

        try {
            // One directory, named two ways: as it was handed over, and as the
            // filesystem resolves it. Windows makes that difference for free,
            // handing the same place back as "RUNNER~1" from one call and
            // "runneradmin" from another.
            $root = ProjectRoot::at($directory . '/src/..');

            expect($root->relative($directory . '/src/../src/Basket.php'))->toBe('src/Basket.php');
            expect($root->relative((string) realpath($directory) . '/src/Basket.php'))->toBe('src/Basket.php');
        } finally {
            @rmdir($directory . '/src');
            @rmdir($directory);
        }
    });

    it('takes the working directory as the root when asked for here', function () {
        expect(ProjectRoot::here()->relative((string) getcwd() . '/src/Basket.php'))->toBe('src/Basket.php');
    });

});
