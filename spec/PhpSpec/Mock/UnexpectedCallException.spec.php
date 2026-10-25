<?php

use PhpSpec\Mock\UnexpectedCallException;
use PhpSpec\Report\DoubleLabels;

interface UnexpectedCallExceptionSpecMenu {}

describe(UnexpectedCallException::class, function () {

    it('names an object argument as a failure names it, a double by its name', function () {
        $saved = DoubleLabels::save();
        DoubleLabels::start();

        try {
            $menu = mock(UnexpectedCallExceptionSpecMenu::class);
            $message = UnexpectedCallException::to('App\Till', 'open', [$menu, new ArrayObject()], getcwd() . '/src/App/Till.php', 8)->getMessage();

            expect($message)->toContain('App\Till::open(UnexpectedCallExceptionSpecMenu double #1, ArrayObject) was called but not expected');
        } finally {
            DoubleLabels::restore($saved);
        }
    });

    it('names the file relative to the project, however the platform spelled the path', function () {
        $message = UnexpectedCallException::to('App\Menu', 'priceOf', ['mocha'], getcwd() . '/src/App/Till.php', 8)->getMessage();
        expect($message)->toContain('App\Menu::priceOf("mocha") was called but not expected, at src/App/Till.php:8.');

        $spelledByWindows = str_replace('/', '\\', getcwd()) . '\\src\\App\\Till.php';
        expect(UnexpectedCallException::to('App\Menu', 'priceOf', [], $spelledByWindows, 8)->getMessage())->toContain(' at src/App/Till.php:8.');
    });

    it('keeps the whole path of a file outside the project, spelled with forward slashes', function () {
        expect(UnexpectedCallException::to('App\Menu', 'priceOf', [], '/elsewhere/Till.php', 8)->getMessage())->toContain(' at /elsewhere/Till.php:8.');
        expect(UnexpectedCallException::to('App\Menu', 'priceOf', [], 'C:\\elsewhere\\Till.php', 8)->getMessage())->toContain(' at C:/elsewhere/Till.php:8.');
    });
});
