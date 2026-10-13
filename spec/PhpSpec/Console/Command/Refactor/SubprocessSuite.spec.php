<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\SubprocessSuite;

describe(SubprocessSuite::class, function () {

    beforeEach(function () {
        $this->project = sys_get_temp_dir() . '/phpspec_suite_' . uniqid();
        mkdir($this->project . '/spec', 0777, true);
        file_put_contents($this->project . '/spec/Green.spec.php', "<?php\ndescribe('Green', function () {\n    it('passes', fn() => expect(true)->toBeTrue());\n});\n");
    });

    afterEach(function () {
        foreach (['/spec/*', '/altspec/*', '/conf/*', '/*.yml'] as $pattern) {
            foreach (glob($this->project . $pattern) ?: [] as $file) {
                unlink($file);
            }
        }
        foreach (glob($this->project . '/.phpspec/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (['/spec', '/altspec', '/conf', '/.phpspec', ''] as $dir) {
            if (is_dir($this->project . $dir)) {
                rmdir($this->project . $dir);
            }
        }
    });

    it('runs the whole spec suite of the project it is given, and reads it green', function () {
        expect((new SubprocessSuite($this->project, new Configuration()))->check()->green())->toBeTrue();
    });

    it('reads which spec file a failure sits in', function () {
        file_put_contents($this->project . '/spec/Red.spec.php', "<?php\ndescribe('Red', function () {\n    it('fails', fn() => expect(1)->toBe(2));\n});\n");

        $check = (new SubprocessSuite($this->project, new Configuration()))->check();

        expect($check->green())->toBeFalse();
        expect($check->confinedTo(['spec/Red.spec.php']))->toBeTrue();
        expect($check->failures()[0])->toContain('spec/Red.spec.php:3');
    });

    it('runs every example, whatever the configuration stops on', function () {
        file_put_contents($this->project . '/phpspec.yml', "stop_on_warning: true\n");
        file_put_contents($this->project . '/spec/AWarns.spec.php', "<?php\ndescribe('AWarns', function () {\n    it('warns', function () { trigger_error('careful', E_USER_WARNING); expect(true)->toBeTrue(); });\n});\n");
        file_put_contents($this->project . '/spec/Red.spec.php', "<?php\ndescribe('Red', function () {\n    it('fails', fn() => expect(1)->toBe(2));\n});\n");

        $check = (new SubprocessSuite($this->project, Configuration::load($this->project)))->check();

        expect($check->green())->toBeFalse();
        expect($check->failures()[0])->toContain('spec/Red.spec.php:3');
    });

    it('keeps none of the offers the run could make: nobody will accept them', function () {
        file_put_contents($this->project . '/spec/Red.spec.php', "<?php\ndescribe('Red', function () {\n    it('needs a class', fn() => expect(new App\\Missing())->toBeNull());\n});\n");

        (new SubprocessSuite($this->project, new Configuration()))->check();

        expect(file_exists($this->project . '/.phpspec/offers.json'))->toBeFalse();
    });

    it('reads the configuration file it was given, not the one the project would find', function () {
        mkdir($this->project . '/conf');
        mkdir($this->project . '/altspec');
        file_put_contents($this->project . '/conf/qa.yml', "spec_path: altspec\n");
        file_put_contents($this->project . '/altspec/Red.spec.php', "<?php\ndescribe('Red', function () {\n    it('fails', fn() => expect(1)->toBe(2));\n});\n");

        $check = (new SubprocessSuite($this->project, Configuration::load($this->project, configFile: $this->project . '/conf/qa.yml')))->check();

        expect($check->failures()[0])->toContain('altspec/Red.spec.php:3');
    });

    it('collects coverage in a project that has guard on, so guard can judge the run', function () {
        if (!extension_loaded('pcov') && !extension_loaded('xdebug')) {
            skip('No coverage engine is loaded.');
        }
        file_put_contents($this->project . '/spec/Green.spec.php', "<?php\ndescribe('Green', function () {\n    it('runs under coverage', fn() => expect(filter_var(ini_get('pcov.enabled'), FILTER_VALIDATE_BOOL) || ini_get('xdebug.mode') === 'coverage')->toBeTrue());\n});\n");

        expect((new SubprocessSuite($this->project, new Configuration(['guard' => ['status' => 'active']])))->check()->failures())->toBe([]);
        expect((new SubprocessSuite($this->project, new Configuration()))->check()->failures()[0])->toContain('Green > runs under coverage');
    });
});
