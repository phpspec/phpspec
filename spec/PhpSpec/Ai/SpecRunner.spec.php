<?php

use PhpSpec\Ai\SpecRunner;

describe(SpecRunner::class, function () {

    it('instantiates', function () {
        expect(class_exists(SpecRunner::class))->toBeTrue();
    });

    it('runs a passing spec file and returns exit code 0', function () {
        $tmp = tempnam(sys_get_temp_dir(), 'phpspec_sr_') . '.spec.php';
        file_put_contents($tmp, <<<'PHP'
        <?php
        describe('SpecRunnerTarget', function() {
            it('passes', fn() => expect(true)->toBeTrue());
        });
        PHP);

        [$exitCode, $output] = SpecRunner::run($tmp);
        unlink($tmp);

        expect($exitCode)->toBe(0);
        expect($output)->toContain('1 example');
    });

    it('runs a failing spec file and returns non-zero exit code', function () {
        $tmp = tempnam(sys_get_temp_dir(), 'phpspec_sr_') . '.spec.php';
        file_put_contents($tmp, <<<'PHP'
        <?php
        describe('SpecRunnerFail', function() {
            it('fails', fn() => expect(true)->toBeFalse());
        });
        PHP);

        [$exitCode, $output] = SpecRunner::run($tmp);
        unlink($tmp);

        expect($exitCode)->not()->toBe(0);
        expect($output)->toContain('1 example');
    });

    it('returns output for non-existent spec path', function () {
        [$exitCode, $output] = SpecRunner::run('/nonexistent/path/spec.php');
        expect($output)->toContain('No specs found');
    });
});
