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
        $real = sys_get_temp_dir() . '/phpspec_root_' . getmypid();
        $alias = sys_get_temp_dir() . '/phpspec_alias_' . getmypid();
        @mkdir($real . '/src', 0777, true);
        @symlink($real, $alias);

        try {
            $root = ProjectRoot::at($alias);

            // Named through the alias, and through what it resolves to.
            expect($root->relative($alias . '/src/Basket.php'))->toBe('src/Basket.php');
            expect($root->relative((string) realpath($real) . '/src/Basket.php'))->toBe('src/Basket.php');
        } finally {
            @unlink($alias);
            @rmdir($real . '/src');
            @rmdir($real);
        }
    });

    it('takes the working directory as the root when asked for here', function () {
        expect(ProjectRoot::here()->relative((string) getcwd() . '/src/Basket.php'))->toBe('src/Basket.php');
    });

});
