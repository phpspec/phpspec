<?php

use PhpSpec\Parallel\ParallelRunner;
use PhpSpec\SeededShuffle;
use PhpSpec\StopConditions;
use PhpSpec\TemporaryDirectory;

describe(ParallelRunner::class, function () {
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

    context('handing the files over', function () {
        this('handed', fn() => function (array $sizes, ?int $seed): array {
            $directory = TemporaryDirectory::create('phpspec_parallel_order_');
            $log = $directory->path . '/handed.log';
            $worker = $directory->path . '/worker.php';
            file_put_contents($worker, '<?php while (($line = fgets(STDIN)) !== false) { file_put_contents(' . var_export($log, true) . ', basename(json_decode($line, true)["give"][0]) . "\\n", FILE_APPEND); echo "{\\"wire\\":1,\\"ran\\":true}\\n"; } echo "{\\"wire\\":1,\\"end\\":true}\\n";');
            $targets = [];

            foreach ($sizes as $name => $size) {
                file_put_contents($directory->path . '/' . $name, str_repeat('x', $size));
                $targets[$directory->path . '/' . $name] = [$directory->path . '/' . $name];
            }

            try {
                iterator_to_array((new ParallelRunner($targets, 1, phpspecBin: $worker, seed: $seed))->stream(), false);

                return file($log, FILE_IGNORE_NEW_LINES);
            } finally {
                $directory->remove();
            }
        });

        it('hands the largest file over first, so the longest to run is not the last to start', function () {
            expect(($this->handed)(['a.spec.php' => 10, 'b.spec.php' => 30, 'c.spec.php' => 20, 'd.spec.php' => 30], null))->toBe(['b.spec.php', 'd.spec.php', 'c.spec.php', 'a.spec.php']);
        });

        it('hands the files over in the order the seed of a random run gives', function () {
            $sizes = ['a.spec.php' => 10, 'b.spec.php' => 30, 'c.spec.php' => 20, 'd.spec.php' => 40];

            expect(($this->handed)($sizes, 42))->toBe((new SeededShuffle(42))->of(array_keys($sizes)));
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
                $runner = new ParallelRunner([$tmpDir . '/spec/Test.spec.php' => [$tmpDir . '/spec/Test.spec.php']], 1);
                $results = iterator_to_array($runner->stream(), false);

                expect(count($results))->toBeGreaterThan(0);
            } finally {
                chdir($savedCwd);
                exec('rm -rf ' . escapeshellarg($tmpDir));
            }
        });

        it('says which workers never handed over the coverage they were asked for', function () {
            $tmpDir = sys_get_temp_dir() . '/phpspec_parallel_' . uniqid();
            mkdir($tmpDir . '/spec', 0777, true);
            mkdir($tmpDir . '/src', 0777, true);
            symlink(realpath(__DIR__ . '/../../../vendor'), $tmpDir . '/vendor');

            file_put_contents($tmpDir . '/spec/Dying.spec.php', <<<'PHP'
            <?php
            describe('Dying', function () {
                it('takes the worker down', function () {
                    exit(3);
                });
            });
            PHP);

            $savedCwd = getcwd();
            chdir($tmpDir);

            try {
                $runner = new ParallelRunner([$tmpDir . '/spec/Dying.spec.php' => [$tmpDir . '/spec/Dying.spec.php']], 1, coveragePartialDir: $tmpDir . '/coverage');
                iterator_to_array($runner->stream(), false);

                expect($runner->lostCoverage())->toHaveCount(1);
                expect($runner->lostCoverage()[0])->toStartWith('Coverage is incomplete: a worker exited with code');
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
                $runner = new ParallelRunner(array_combine($paths, array_map(static fn(string $path): array => [$path], $paths)), 2);
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
                $runner = new ParallelRunner([$tmpDir . '/spec/Fail.spec.php' => [$tmpDir . '/spec/Fail.spec.php']], 1, $stop);
                $results = iterator_to_array($runner->stream(), false);

                expect(count($results))->toBeGreaterThan(0);
            } finally {
                chdir($savedCwd);
                exec('rm -rf ' . escapeshellarg($tmpDir));
            }
        });
    });

});
