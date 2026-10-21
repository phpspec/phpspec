<?php

use PhpSpec\Coverage\ExclusionPattern;

describe(ExclusionPattern::class, function () {

    $paths = fn(string $entry, array $paths): array => array_map(fn(string $path) => (new ExclusionPattern($entry))->matchesPath($path), $paths);

    context('a path', function () use ($paths) {
        it('matches a file, by its path from the project root', function () use ($paths) {
            expect($paths('src/Kernel.php', ['src/Kernel.php', 'src/Kernel.phpx', 'src/App/Kernel.php']))->toBe([true, false, false]);
            expect($paths('./src/Kernel.php', ['src/Kernel.php']))->toBe([true]);
        });

        it('matches everything under a directory, with or without its trailing slash', function () use ($paths) {
            expect($paths('src/Migrations/', ['src/Migrations/V1.php', 'src/Migrations/Old/V0.php', 'src/MigrationsOld/V1.php']))->toBe([true, true, false]);
            expect($paths('src/Migrations', ['src/Migrations/V1.php', 'src/MigrationsOld/V1.php']))->toBe([true, false]);
        });

        it('matches a glob, * within a directory and ** across directories', function () use ($paths) {
            expect($paths('src/*.php', ['src/a.php', 'src/App/a.php']))->toBe([true, false]);
            expect($paths('src/**/Generated/*.php', ['src/App/Generated/Mapper.php', 'src/Generated/Mapper.php', 'src/App/Generated/Deep/Mapper.php']))->toBe([true, true, false]);
        });

        it('names nothing in the code', function () {
            expect((new ExclusionPattern('src/Kernel.php'))->matchesName('class', 'src/Kernel.php'))->toBeFalse();
        });
    });

    context('a name', function () {
        it('matches a class, trait or enum, whatever its case and leading backslash', function () {
            $pattern = new ExclusionPattern('App\Http\Kernel');

            expect($pattern->matchesName('class', 'app\http\kernel'))->toBeTrue();
            expect((new ExclusionPattern('\App\Http\Kernel'))->matchesName('class', 'App\Http\Kernel'))->toBeTrue();
            expect($pattern->matchesName('class', 'App\Http\KernelTest'))->toBeFalse();
            expect($pattern->matchesName('method', 'App\Http\Kernel'))->toBeFalse();
        });

        it('matches every class under a namespace with *', function () {
            $pattern = new ExclusionPattern('App\Legacy\*');

            expect($pattern->matchesName('class', 'App\Legacy\Converter'))->toBeTrue();
            expect($pattern->matchesName('class', 'App\Legacy\Deep\Mapper'))->toBeTrue();
            expect($pattern->matchesName('class', 'App\LegacyOther'))->toBeFalse();
        });

        it('matches a method, or every method of a class with *', function () {
            expect((new ExclusionPattern('App\Clock::now'))->matchesName('method', 'App\Clock::NOW'))->toBeTrue();
            expect((new ExclusionPattern('App\Clock::now'))->matchesName('class', 'App\Clock'))->toBeFalse();
            expect((new ExclusionPattern('App\Clock::*'))->matchesName('method', 'App\Clock::today'))->toBeTrue();
        });

        it('matches a function written with its parentheses', function () {
            expect((new ExclusionPattern('App\shout()'))->matchesName('function', 'App\shout'))->toBeTrue();
            expect((new ExclusionPattern('shout()'))->matchesName('function', 'App\shout'))->toBeFalse();
            expect((new ExclusionPattern('App\shout()'))->matchesName('class', 'App\shout'))->toBeFalse();
        });

        it('names no path', function () {
            expect((new ExclusionPattern('App\Legacy\*'))->matchesPath('src/App/Legacy/Converter.php'))->toBeFalse();
        });
    });
});
