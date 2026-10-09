<?php

use PhpSpec\Mock\UnexpectedCallException;

describe(UnexpectedCallException::class, function () {

    it('names the file relative to the project, however the platform spelled the path', function () {
        $message = UnexpectedCallException::to('App\Menu', 'priceOf', ['mocha'], getcwd() . '/src/App/Till.php', 8)->getMessage();
        expect($message)->toContain('App\Menu::priceOf("mocha") was called but not expected, at src/App/Till.php:8.');

        $spelledByWindows = str_replace('/', '\\', getcwd()) . '\\src\\App\\Till.php';
        expect(UnexpectedCallException::to('App\Menu', 'priceOf', [], $spelledByWindows, 8)->getMessage())->toContain(' at src/App/Till.php:8.');
    });

    it('keeps the whole path of a file outside the project', function () {
        expect(UnexpectedCallException::to('App\Menu', 'priceOf', [], '/elsewhere/Till.php', 8)->getMessage())->toContain(' at /elsewhere/Till.php:8.');
    });
});
