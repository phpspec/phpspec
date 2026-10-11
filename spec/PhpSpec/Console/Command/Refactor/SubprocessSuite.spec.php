<?php

use PhpSpec\Console\Command\Refactor\SubprocessSuite;

describe(SubprocessSuite::class, function () {

    beforeEach(function () {
        $this->project = sys_get_temp_dir() . '/phpspec_suite_' . uniqid();
        mkdir($this->project . '/spec', 0777, true);
        file_put_contents($this->project . '/spec/Green.spec.php', "<?php\ndescribe('Green', function () {\n    it('passes', fn() => expect(true)->toBeTrue());\n});\n");
    });

    afterEach(function () {
        foreach (glob($this->project . '/spec/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->project . '/spec');
        rmdir($this->project);
    });

    it('runs the whole spec suite of the project it is given, and reads it green', function () {
        expect((new SubprocessSuite($this->project))->check()->green())->toBeTrue();
    });

    it('reads which spec file a failure sits in', function () {
        file_put_contents($this->project . '/spec/Red.spec.php', "<?php\ndescribe('Red', function () {\n    it('fails', fn() => expect(1)->toBe(2));\n});\n");

        $check = (new SubprocessSuite($this->project))->check();

        expect($check->green())->toBeFalse();
        expect($check->confinedTo(['spec/Red.spec.php']))->toBeTrue();
        expect($check->report())->toContain('spec/Red.spec.php:3');
    });

    it('collects coverage in a project that has guard on, so guard can judge the run', function () {
        if (!extension_loaded('xdebug')) {
            skip('Xdebug is not loaded.');
        }
        file_put_contents($this->project . '/spec/Green.spec.php', "<?php\ndescribe('Green', function () {\n    it('runs under coverage', fn() => expect(ini_get('xdebug.mode'))->toBe('coverage'));\n});\n");

        expect((new SubprocessSuite($this->project, guarded: true))->check()->report())->toBe('');
        expect((new SubprocessSuite($this->project))->check()->report())->toContain('Green > runs under coverage');
    });
});
