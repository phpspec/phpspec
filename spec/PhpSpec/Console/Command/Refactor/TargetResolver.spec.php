<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\TargetResolver;
use PhpSpec\Console\Command\Refactor\UnresolvedTargetException;
use PhpSpec\Filesystem;

describe(TargetResolver::class, function () {

    // A project with App\ under src/App and Acme\ under lib/Acme, whose files
    // are written at the times given.
    let('project', fn() => new ArrayObject([
        'files' => [
            '/proj/src/App/Basket.php' => ["<?php\nnamespace App;\n\nclass Basket {}\n", 100],
            '/proj/src/App/Till.php' => ["<?php\nnamespace App;\n\nclass Till {}\n", 200],
            '/proj/lib/Acme/Ledger.php' => ["<?php\nnamespace Acme;\n\nclass Ledger {}\n", 300],
            '/proj/spec/App/Basket.spec.php' => ["<?php\n", 50],
            '/proj/spec/App/Till.spec.php' => ["<?php\n", 50],
            '/proj/spec/Acme/Ledger.spec.php' => ["<?php\n", 50],
        ],
    ]));
    let('resolver', function (Filesystem $fs) {
        $files = fn(): array => $this->project['files'];
        $children = function (string $dir) use ($files): array {
            $entries = [];
            foreach (array_keys($files()) as $path) {
                if (str_starts_with($path, $dir . '/')) {
                    $entries[explode('/', substr($path, strlen($dir) + 1))[0]] = true;
                }
            }

            return array_keys($entries);
        };
        allow($fs->exists())->toReturnUsing(fn(string $p): bool => isset($files()[$p]) || $children($p) !== []);
        allow($fs->isDir())->toReturnUsing(fn(string $p): bool => $children($p) !== []);
        allow($fs->scandir())->toReturnUsing($children);
        allow($fs->read())->toReturnUsing(fn(string $p): string => $files()[$p][0] ?? '');
        allow($fs->mtime())->toReturnUsing(fn(string $p): int => $files()[$p][1] ?? 0);

        return new TargetResolver(new Configuration(['autoload' => ['App\\' => 'src/App', 'Acme\\' => 'lib/Acme']]), $fs, '/proj');
    });

    context('with no argument', function () {

        it('takes the source modified last under the source path, and its spec', function () {
            $target = $this->resolver->resolve(null);

            expect($target->fqcn)->toBe('App\\Till');
            expect($target->sourceFile)->toBe('/proj/src/App/Till.php');
            expect($target->specFile)->toBe('/proj/spec/App/Till.spec.php');
            expect($target->method)->toBeNull();
        });

        it('refuses the source modified last when it has no spec, saying how to give it one', function () {
            $files = $this->project['files'];
            unset($files['/proj/spec/App/Till.spec.php']);
            $this->project['files'] = $files;

            expect(fn() => $this->resolver->resolve(null))->toThrow(UnresolvedTargetException::class, 'App\\Till has no spec, so nothing would catch a refactoring that broke it. Describe it first: phpspec describe App\\Till');
        });

        it('refuses when there is no source, naming where it looked', function () {
            $this->project['files'] = [];

            expect(fn() => $this->resolver->resolve(null))->toThrow(UnresolvedTargetException::class, 'No source to refactor under src.');
        });

        it('refuses a source modified last that declares no class', function () {
            $files = $this->project['files'];
            $files['/proj/src/App/helpers.php'] = ["<?php\nfunction helper(): void {}\n", 300];
            $this->project['files'] = $files;

            expect(fn() => $this->resolver->resolve(null))->toThrow(UnresolvedTargetException::class, 'src/App/helpers.php, the source modified last, declares no class to refactor.');
        });
    });

    context('with a target', function () {

        it('resolves a class to its source file, by the layout, and its spec', function () {
            $target = $this->resolver->resolve('App\\Basket');

            expect($target->fqcn)->toBe('App\\Basket');
            expect($target->sourceFile)->toBe('/proj/src/App/Basket.php');
            expect($target->specFile)->toBe('/proj/spec/App/Basket.spec.php');
        });

        it('resolves a short name to the class by that name modified last under the source path', function () {
            $files = $this->project['files'];
            $files['/proj/src/App/Legacy/Till.php'] = ["<?php\nnamespace App\\Legacy;\n\nclass Till {}\n", 250];
            $files['/proj/spec/App/Legacy/Till.spec.php'] = ["<?php\n", 50];
            $this->project['files'] = $files;

            $target = $this->resolver->resolve('Till::total');

            expect($target->fqcn)->toBe('App\\Legacy\\Till');
            expect($target->sourceFile)->toBe('/proj/src/App/Legacy/Till.php');
            expect($target->method)->toBe('total');
            expect($this->resolver->resolve('Basket')->fqcn)->toBe('App\\Basket');
        });

        it('refuses a short name no class under the source path answers to, one elsewhere included', function () {
            expect(fn() => $this->resolver->resolve('Ledger'))->toThrow(UnresolvedTargetException::class, 'No class named Ledger under src. Describe it first: phpspec describe Ledger');
        });

        it('keeps the method a class::method target focuses on', function () {
            $target = $this->resolver->resolve('App\\Basket::total');

            expect($target->fqcn)->toBe('App\\Basket');
            expect($target->method)->toBe('total');
        });

        it('resolves a spec file to the class it describes', function () {
            $target = $this->resolver->resolve('spec/Acme/Ledger.spec.php');

            expect($target->fqcn)->toBe('Acme\\Ledger');
            expect($target->sourceFile)->toBe('/proj/lib/Acme/Ledger.php');
            expect($target->specFile)->toBe('/proj/spec/Acme/Ledger.spec.php');
        });

        it('refuses a class whose source is not there', function () {
            expect(fn() => $this->resolver->resolve('App\\Missing'))->toThrow(UnresolvedTargetException::class, 'Source file not found: /proj/src/App/Missing.php');
        });

        it('refuses a class with no spec as it refuses the source modified last', function () {
            $files = $this->project['files'];
            unset($files['/proj/spec/App/Basket.spec.php']);
            $this->project['files'] = $files;

            expect(fn() => $this->resolver->resolve('App\\Basket'))->toThrow(UnresolvedTargetException::class, 'App\\Basket has no spec, so nothing would catch a refactoring that broke it. Describe it first: phpspec describe App\\Basket');
        });
    });
});
