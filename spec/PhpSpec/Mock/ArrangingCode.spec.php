<?php

use PhpSpec\Mock\ArrangingCode;

describe('ArrangingCode', function () {

    beforeEach(function () {
        $this->saved = ArrangingCode::roots();
        ArrangingCode::reset();
    });

    afterEach(function () {
        ArrangingCode::under(...$this->saved);
    });

    it('takes every file for arranging code until told where the spec code lives', function () {
        expect(ArrangingCode::includes(__FILE__))->toBeTrue();
        expect(ArrangingCode::includes('/nowhere/at/all.php'))->toBeTrue();
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
