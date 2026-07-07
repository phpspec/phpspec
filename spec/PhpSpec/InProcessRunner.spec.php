<?php

use PhpSpec\ClassConflictException;
use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\InProcessResult;
use PhpSpec\InProcessRunner;

describe(InProcessRunner::class, function () {
    let("inProcessRunner", fn() => new InProcessRunner());

    it("instantiates", fn() =>
        expect($this->inProcessRunner)->toBeAnInstanceOf(InProcessRunner::class));

    describe('::parseArgs', function () {
        let('parseArgs', function () {
            $ref = new ReflectionMethod(InProcessRunner::class, 'parseArgs');
            return $ref->getClosure();
        });

        it('parses a simple run command', function () {
            $result = ($this->parseArgs)('run');
            expect($result)->toBe(['command' => 'run']);
        });

        it('parses run with a long option flag', function () {
            $result = ($this->parseArgs)('run --stop-on-failure');
            expect($result)->toBe([
                'command' => 'run',
                '--stop-on-failure' => true,
            ]);
        });

        it('parses run with a long option that has a value via equals', function () {
            $result = ($this->parseArgs)('run --format=pretty');
            expect($result)->toBe([
                'command' => 'run',
                '--format' => 'pretty',
            ]);
        });

        it('parses run with a long option that has a value via space', function () {
            $result = ($this->parseArgs)('run --format pretty');
            expect($result)->toBe([
                'command' => 'run',
                '--format' => 'pretty',
            ]);
        });

        it('parses run with a short option flag', function () {
            $result = ($this->parseArgs)('run -v');
            expect($result)->toBe([
                'command' => 'run',
                '-v' => true,
            ]);
        });

        it('aggregates repeated options into arrays, preserving order', function () {
            $result = ($this->parseArgs)('run -f pretty -o std -f html -o report.html');
            expect($result)->toBe([
                'command' => 'run',
                '-f' => ['pretty', 'html'],
                '-o' => ['std', 'report.html'],
            ]);
        });

        it('parses run with a short option and value', function () {
            $result = ($this->parseArgs)('run -f pretty');
            expect($result)->toBe([
                'command' => 'run',
                '-f' => 'pretty',
            ]);
        });

        it('parses run with a positional file argument', function () {
            $result = ($this->parseArgs)('run spec/App/Calculator.spec.php');
            expect($result)->toBe([
                'command' => 'run',
                'files' => ['spec/App/Calculator.spec.php'],
            ]);
        });

        it('parses run with multiple positional file arguments', function () {
            $result = ($this->parseArgs)('run spec/Foo.spec.php spec/Bar.spec.php');
            expect($result)->toBe([
                'command' => 'run',
                'files' => ['spec/Foo.spec.php', 'spec/Bar.spec.php'],
            ]);
        });

        it('parses describe with a class argument', function () {
            $result = ($this->parseArgs)('describe App\Calculator');
            expect($result)->toBe([
                'command' => 'describe',
                'class' => 'App\Calculator',
            ]);
        });

        it('parses describe with a class and an option', function () {
            $result = ($this->parseArgs)('describe App\Calculator -e add');
            expect($result)->toBe([
                'command' => 'describe',
                'class' => 'App\Calculator',
                '-e' => 'add',
            ]);
        });

        it('parses exemplify with class and method', function () {
            $result = ($this->parseArgs)('exemplify App\Calculator add');
            expect($result)->toBe([
                'command' => 'exemplify',
                'class' => 'App\Calculator',
                'method' => 'add',
            ]);
        });

        it('parses refactor with a target', function () {
            $result = ($this->parseArgs)('refactor App\Calculator');
            expect($result)->toBe([
                'command' => 'refactor',
                'target' => 'App\Calculator',
            ]);
        });

        it('handles extra whitespace gracefully', function () {
            $result = ($this->parseArgs)('  run   --verbose  ');
            expect($result)->toBe([
                'command' => 'run',
                '--verbose' => true,
            ]);
        });
    });

    describe('::checkForClassConflicts', function () {
        let('checkForClassConflicts', function () {
            $ref = new ReflectionMethod(InProcessRunner::class, 'checkForClassConflicts');
            return $ref->getClosure();
        });

        it('does nothing when src directory does not exist', function () {
            $dir = sys_get_temp_dir() . '/phpspec_test_no_src_' . uniqid();
            mkdir($dir);
            // Should not throw
            ($this->checkForClassConflicts)($dir);
            expect(true)->toBeTrue();
            rmdir($dir);
        });

        it('does nothing when src directory has no PHP files', function () {
            $dir = sys_get_temp_dir() . '/phpspec_test_empty_src_' . uniqid();
            mkdir($dir . '/src', 0777, true);
            // Should not throw
            ($this->checkForClassConflicts)($dir);
            expect(true)->toBeTrue();
            rmdir($dir . '/src');
            rmdir($dir);
        });
    });

    describe('::run', function () {
        it('returns an InProcessResult', function () {
            $dir = sys_get_temp_dir() . '/phpspec_inprocess_' . uniqid();
            mkdir($dir . '/spec', 0777, true);
            mkdir($dir . '/src', 0777, true);

            file_put_contents($dir . '/phpspec.json', json_encode([
                'suites' => ['main' => ['spec_prefix' => 'spec', 'src_path' => 'src']],
            ]));

            $result = InProcessRunner::run($dir, 'run');

            expect($result)->toBeAnInstanceOf(InProcessResult::class);
            expect($result->exitCode)->toBeOfType('int');
            expect($result->output)->toBeOfType('string');

            // Cleanup
            @unlink($dir . '/phpspec.json');
            @rmdir($dir . '/spec');
            @rmdir($dir . '/src');
            @rmdir($dir);
        });

        it('isolates the coverage registry from the inner run and restores it', function (CoverageDriver $driver) {
            $cycles = [];
            allow($driver->start())->toReturnUsing(function () use (&$cycles) {
                $cycles[] = 'start';
            });
            allow($driver->stop())->toReturnUsing(function () use (&$cycles) {
                $cycles[] = 'stop';
                return [];
            });
            $outerCollector = new PerExampleCollector($driver);
            CoverageRegistry::activate($outerCollector);

            $dir = sys_get_temp_dir() . '/phpspec_inprocess_cov_' . uniqid();
            mkdir($dir . '/spec/App', 0777, true);
            mkdir($dir . '/src', 0777, true);
            file_put_contents($dir . '/spec/App/Inner.spec.php', <<<'PHP'
            <?php
            describe('Inner', function () {
                it('passes', function () {
                    expect(true)->toBeTrue();
                });
            });
            PHP);

            try {
                InProcessRunner::run($dir, 'run');

                expect(CoverageRegistry::collector())->toBe($outerCollector);
                expect($cycles)->toBe([]);
            } finally {
                CoverageRegistry::reset();
                @unlink($dir . '/spec/App/Inner.spec.php');
                @rmdir($dir . '/spec/App');
                @rmdir($dir . '/spec');
                @rmdir($dir . '/src');
                @rmdir($dir);
            }
        });
    });
});
