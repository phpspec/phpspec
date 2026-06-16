<?php

use PhpSpec\Parallel\ParallelRunner;
use PhpSpec\StopConditions;

describe(ParallelRunner::class, function () {
    context('detectCpuCount', function () {
        it('returns at least 1', function () {
            expect(ParallelRunner::detectCpuCount())->toBeGreaterThan(0);
        });
    });

    context('constructor', function () {
        it('enforces minimum of 1 worker', function () {
            $runner = new ParallelRunner([], 0);
            $results = iterator_to_array($runner->stream(), false);
            expect($results)->toHaveCount(0);
        });

        it('accepts null workers for auto-detect', function () {
            $runner = new ParallelRunner([], null);
            $results = iterator_to_array($runner->stream(), false);
            expect($results)->toHaveCount(0);
        });
    });

    context('with empty paths', function () {
        it('yields no results', function () {
            $runner = new ParallelRunner([], 1);
            $results = iterator_to_array($runner->stream(), false);

            expect($results)->toHaveCount(0);
        });
    });

    context('with spec files', function () {
        it('runs a simple spec and returns results', function () {
            $tmpDir = sys_get_temp_dir() . '/phpspec_parallel_' . uniqid();
            mkdir($tmpDir . '/spec', 0777, true);
            mkdir($tmpDir . '/src', 0777, true);
            symlink(realpath(__DIR__ . '/../../../vendor'), $tmpDir . '/vendor');

            file_put_contents($tmpDir . '/spec/Test.spec.php', <<<'PHP'
            <?php
            describe('Test', function () {
                it('passes', function () {
                    expect(true)->toBeTrue();
                });
            });
            PHP);

            $savedCwd = getcwd();
            chdir($tmpDir);

            try {
                $runner = new ParallelRunner([$tmpDir . '/spec/Test.spec.php'], 1);
                $results = iterator_to_array($runner->stream(), false);

                expect(count($results))->toBeGreaterThan(0);
            } finally {
                chdir($savedCwd);
                exec('rm -rf ' . escapeshellarg($tmpDir));
            }
        });

        it('distributes specs across workers', function () {
            $tmpDir = sys_get_temp_dir() . '/phpspec_parallel_' . uniqid();
            mkdir($tmpDir . '/spec', 0777, true);
            mkdir($tmpDir . '/src', 0777, true);
            symlink(realpath(__DIR__ . '/../../../vendor'), $tmpDir . '/vendor');

            for ($i = 1; $i <= 3; $i++) {
                file_put_contents($tmpDir . "/spec/Test{$i}.spec.php", <<<PHP
                <?php
                describe('Test{$i}', function () {
                    it('passes', function () {
                        expect(true)->toBeTrue();
                    });
                });
                PHP);
            }

            $savedCwd = getcwd();
            chdir($tmpDir);

            try {
                $paths = [
                    $tmpDir . '/spec/Test1.spec.php',
                    $tmpDir . '/spec/Test2.spec.php',
                    $tmpDir . '/spec/Test3.spec.php',
                ];
                $runner = new ParallelRunner($paths, 2);
                $results = iterator_to_array($runner->stream(), false);

                expect(count($results))->toBe(3);
            } finally {
                chdir($savedCwd);
                exec('rm -rf ' . escapeshellarg($tmpDir));
            }
        });
    });

    context('stop on failure', function () {
        it('stops after first failing spec when stop-on-failure is set', function () {
            $tmpDir = sys_get_temp_dir() . '/phpspec_parallel_' . uniqid();
            mkdir($tmpDir . '/spec', 0777, true);
            mkdir($tmpDir . '/src', 0777, true);
            symlink(realpath(__DIR__ . '/../../../vendor'), $tmpDir . '/vendor');

            file_put_contents($tmpDir . '/spec/Fail.spec.php', <<<'PHP'
            <?php
            describe('Fail', function () {
                it('fails', function () {
                    expect(1)->toBe(2);
                });
            });
            PHP);

            $savedCwd = getcwd();
            chdir($tmpDir);

            try {
                $stop = new StopConditions(onFailure: true);
                $runner = new ParallelRunner([$tmpDir . '/spec/Fail.spec.php'], 1, $stop);
                $results = iterator_to_array($runner->stream(), false);

                expect(count($results))->toBeGreaterThan(0);
            } finally {
                chdir($savedCwd);
                exec('rm -rf ' . escapeshellarg($tmpDir));
            }
        });
    });

    context('shouldStop', function () {
        it('stops on failure when onFailure is set', function () {
            $method = new \ReflectionMethod(ParallelRunner::class, 'shouldStop');

            $failedExample = new \PhpSpec\Result\ExampleResult('fails', [
                \PhpSpec\Result\MatchResult::failed(null, null, 'failed', '', 0),
            ]);
            $spec = new \PhpSpec\Result\SpecificationResult('Spec', [$failedExample]);
            $stop = new StopConditions(onFailure: true);

            expect($method->invoke(null, $spec, $stop))->toBeTrue();
        });

        it('stops on error when onError is set', function () {
            $method = new \ReflectionMethod(ParallelRunner::class, 'shouldStop');

            $errorExample = new \PhpSpec\Result\ExampleResult('errors', [], true);
            $spec = new \PhpSpec\Result\SpecificationResult('Spec', [$errorExample]);
            $stop = new StopConditions(onError: true);

            expect($method->invoke(null, $spec, $stop))->toBeTrue();
        });

        it('stops on skipped when onSkipped is set', function () {
            $method = new \ReflectionMethod(ParallelRunner::class, 'shouldStop');

            $skippedExample = new \PhpSpec\Result\ExampleResult('skipped', [], false, false, true);
            $spec = new \PhpSpec\Result\SpecificationResult('Spec', [$skippedExample]);
            $stop = new StopConditions(onSkipped: true);

            expect($method->invoke(null, $spec, $stop))->toBeTrue();
        });

        it('does not stop on passing results', function () {
            $method = new \ReflectionMethod(ParallelRunner::class, 'shouldStop');

            $passingExample = new \PhpSpec\Result\ExampleResult('passes', []);
            $spec = new \PhpSpec\Result\SpecificationResult('Spec', [$passingExample]);
            $stop = new StopConditions(onFailure: true);

            expect($method->invoke(null, $spec, $stop))->toBeFalse();
        });

        it('stops on step failure when onFailure is set', function () {
            $method = new \ReflectionMethod(ParallelRunner::class, 'shouldStop');

            $failedStep = new \PhpSpec\Result\StepResult('Then it fails', 'failure');
            $scenario = new \PhpSpec\Result\ScenarioResult('Scenario', [$failedStep]);
            $feature = new \PhpSpec\Result\FeatureResult('Feature', [$scenario]);
            $stop = new StopConditions(onFailure: true);

            expect($method->invoke(null, $feature, $stop))->toBeTrue();
        });

        it('does not stop on passing steps', function () {
            $method = new \ReflectionMethod(ParallelRunner::class, 'shouldStop');

            $passingStep = new \PhpSpec\Result\StepResult('Given something', 'passed');
            $scenario = new \PhpSpec\Result\ScenarioResult('Scenario', [$passingStep]);
            $feature = new \PhpSpec\Result\FeatureResult('Feature', [$scenario]);
            $stop = new StopConditions(onFailure: true);

            expect($method->invoke(null, $feature, $stop))->toBeFalse();
        });
    });
});
