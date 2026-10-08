<?php

use PhpSpec\Mock\ArrangingCode;

describe('ArrangingCode', function () {

    beforeEach(function () {
        $this->saved = [ArrangingCode::roots(), ArrangingCode::loadedFiles()];
        ArrangingCode::reset();
    });

    afterEach(function () {
        ArrangingCode::under(...$this->saved[0]);
        ArrangingCode::loaded(...$this->saved[1]);
    });

    it('takes every file for arranging code until told where the spec code lives', function () {
        expect(ArrangingCode::includes(__FILE__))->toBeTrue();
        expect(ArrangingCode::includes('/nowhere/at/all.php'))->toBeTrue();
    });

    it('includes a file PhpSpec loaded as spec code, wherever it sits', function () {
        $probe = tempnam(sys_get_temp_dir(), 'phpspec_probe_') . '.spec.php';
        file_put_contents($probe, '<?php');
        ArrangingCode::under(__DIR__);

        try {
            expect(ArrangingCode::includes($probe))->toBeFalse();

            ArrangingCode::loaded($probe);

            expect(ArrangingCode::includes($probe))->toBeTrue();
            expect(ArrangingCode::loadedFiles())->toBe([realpath($probe)]);

            ArrangingCode::reset();

            expect(ArrangingCode::loadedFiles())->toBe([]);
        } finally {
            unlink($probe);
        }
    });

    it('includes a file under one of its roots', function () {
        ArrangingCode::under(sys_get_temp_dir(), __DIR__);

        expect(ArrangingCode::includes(__FILE__))->toBeTrue();
        expect(ArrangingCode::includes(__DIR__ . '/../Mock/Double.spec.php'))->toBeTrue();
    });

    it('excludes a file anywhere else, and the code eval() ran', function () {
        ArrangingCode::under(__DIR__);

        expect(ArrangingCode::includes(dirname(__DIR__) . '/Specification/Context.spec.php'))->toBeFalse();
        expect(ArrangingCode::includes("spec/Mock/Double.php(42) : eval()'d code"))->toBeFalse();
    });

    it('bounds a root at the directory, not at the letters of its name', function () {
        ArrangingCode::under(__DIR__);

        expect(ArrangingCode::includes(__DIR__ . 'ery/Other.php'))->toBeFalse();
    });

    it('tells where it was told the spec code lives, resolved', function () {
        ArrangingCode::under(__DIR__ . '/../Mock');

        expect(ArrangingCode::roots())->toBe([__DIR__]);
    });
});
