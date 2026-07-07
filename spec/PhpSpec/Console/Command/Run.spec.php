<?php

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run;
use PhpSpec\Filesystem;
use PhpSpec\Loader;
use PhpSpec\Runner;

describe(Run::class, function () {

    context('execute', function () {

        beforeEach(function (Filesystem $execFs) {
            allow($execFs->exists())->toReturn(false);
            allow($execFs->isFile())->toReturnUsing(fn() => false);
            allow($execFs->isDir())->toReturnUsing(fn() => false);
        });

        it('runs with empty spec directory', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute([]);
            $output = $tester->getDisplay();
            expect($output)->toContain('No specs found');
        });

        it('runs with dot format', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--format' => 'dot']);
            $output = $tester->getDisplay();
            expect($output)->toContain('0 specs');
        });

        it('runs with tap format', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--format' => 'tap']);
            $output = $tester->getDisplay();
            expect($output)->toContain('TAP version 13');
        });

        it('runs with junit format', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--format' => 'junit']);
            $output = $tester->getDisplay();
            expect($output)->toContain('<?xml');
        });

        it('runs with html format', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--format' => 'html']);
            expect($tester->getDisplay())->toContain('<!DOCTYPE html>');
        });

        it('rejects unknown formats', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $exitCode = $tester->execute(['--format' => 'nope']);
            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('Unknown format: nope');
        });

        it('pairs each -o file with its format by position', function (Filesystem $execFs) {
            $dir = sys_get_temp_dir() . '/phpspec_reports_' . uniqid();
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            try {
                $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
                $tester->execute([
                    '--format' => ['pretty', 'html', 'junit'],
                    '--out' => ['std', $dir . '/report.html', $dir . '/report.xml'],
                ]);
                $output = $tester->getDisplay();

                expect($output)->toContain('No specs found');
                expect((string) file_get_contents($dir . '/report.html'))->toContain('<!DOCTYPE html>');
                expect((string) file_get_contents($dir . '/report.xml'))->toContain('<testsuites');
                expect($output)->toContain('Report written to ' . $dir . '/report.html');
            } finally {
                @unlink($dir . '/report.html');
                @unlink($dir . '/report.xml');
                @rmdir($dir);
            }
        });

        it('defaults the console to pretty when every format writes to a file', function (Filesystem $execFs) {
            $dir = sys_get_temp_dir() . '/phpspec_fileonly_' . uniqid();
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            try {
                $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
                $tester->execute([
                    '--format' => ['html'],
                    '--out' => [$dir . '/report.html'],
                ]);
                $output = $tester->getDisplay();

                expect($output)->toContain('No specs found');
                expect($output)->not()->toContain('<!DOCTYPE html>');
                expect((string) file_get_contents($dir . '/report.html'))->toContain('<!DOCTYPE html>');
            } finally {
                @unlink($dir . '/report.html');
                @rmdir($dir);
            }
        });

        it('runs with profile option', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--profile' => '5']);
            expect($tester->getStatusCode())->toBe(0);
        });

        it('runs with random order', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--order' => 'random', '--seed' => '42']);
            $output = $tester->getDisplay();
            expect($output)->toContain('Randomised with seed 42');
        });

        it('runs with stop-on-failure', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--stop-on-failure' => true]);
            expect($tester->getStatusCode())->toBe(0);
        });

        it('runs with filter option', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--filter' => 'NonExistent']);
            expect($tester->getStatusCode())->toBe(0);
        });

        it('filters examples by title and hides specs left with no examples', function (Filesystem $execFs) {
            $dir = sys_get_temp_dir() . '/phpspec_run_filter_' . uniqid();
            mkdir($dir, 0777, true);
            file_put_contents($dir . '/Alpha.spec.php', <<<'PHP'
            <?php
            describe('Alpha', function () {
                it('does the wanted thing', function () { expect(true)->toBeTrue(); });
                it('does another thing', function () { expect(true)->toBeTrue(); });
            });
            PHP);
            file_put_contents($dir . '/Beta.spec.php', <<<'PHP'
            <?php
            describe('Beta', function () {
                it('does something else', function () { expect(true)->toBeTrue(); });
            });
            PHP);

            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader(), new Runner(), $config);

            try {
                $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
                $exitCode = $tester->execute(['files' => [$dir], '--filter' => 'wanted']);
                $output = $tester->getDisplay();

                expect($exitCode)->toBe(0);
                expect($output)->toContain('does the wanted thing');
                expect($output)->not()->toContain('another thing');
                expect($output)->not()->toContain('Beta');
            } finally {
                unlink($dir . '/Alpha.spec.php');
                unlink($dir . '/Beta.spec.php');
                rmdir($dir);
            }
        });

        it('runs a single example addressed by spec path and line', function (Filesystem $execFs) {
            $dir = sys_get_temp_dir() . '/phpspec_run_line_' . uniqid();
            mkdir($dir, 0777, true);
            file_put_contents($dir . '/Picky.spec.php', <<<'PHP'
            <?php
            describe('Picky', function () {
                it('first example', function () { expect(true)->toBeTrue(); });
                it('second example', function () { expect(true)->toBeTrue(); });
            });
            PHP);

            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader(), new Runner(), $config);

            try {
                $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
                $exitCode = $tester->execute(['files' => [$dir . '/Picky.spec.php:4']]);
                $output = $tester->getDisplay();

                expect($exitCode)->toBe(0);
                expect($output)->toContain('second example');
                expect($output)->not()->toContain('first example');
            } finally {
                unlink($dir . '/Picky.spec.php');
                rmdir($dir);
            }
        });

        it('loads bootstrap file when specified', function (Filesystem $execFs) {
            $tmpBootstrap = tempnam(sys_get_temp_dir(), 'phpspec_bs_') . '.php';
            file_put_contents($tmpBootstrap, '<?php // bootstrap loaded');

            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--bootstrap' => $tmpBootstrap]);
            unlink($tmpBootstrap);
            expect($tester->getStatusCode())->toBe(0);
        });

        it('returns error for non-existent bootstrap', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $exitCode = $tester->execute(['--bootstrap' => '/nonexistent/bootstrap.php']);
            expect($exitCode)->toBe(1);
        });

        it('runs with the parallel option on an empty suite', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $exitCode = $tester->execute(['--parallel' => '2']);
            expect($exitCode)->toBe(0);
            expect($tester->getDisplay())->toContain('No specs found');
        });

        it('errors when the paths file does not exist', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $exitCode = $tester->execute(['--paths-from' => 'missing.txt']);
            expect($exitCode)->toBe(1);
            expect($tester->getDisplay())->toContain('missing.txt');
        });

        it('reads spec paths from the paths file', function (Filesystem $execFs) {
            $pathsFile = tempnam(sys_get_temp_dir(), 'phpspec_paths_') . '.txt';
            file_put_contents($pathsFile, "spec/App/NotThere.spec.php\n\n");

            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            try {
                $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
                $exitCode = $tester->execute(['--paths-from' => $pathsFile]);
                expect($exitCode)->toBe(0);
                expect($tester->getDisplay())->toContain('No specs found');
            } finally {
                unlink($pathsFile);
            }
        });

        it('registers PSR-4 autoloader from config', function (Filesystem $execFs) {
            allow($execFs->exists())->toReturnUsing(fn($p) => str_ends_with($p, 'phpspec.json'));
            allow($execFs->read())->toReturn(json_encode(['autoload' => ['TestNs\\' => 'src/']]));
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute([]);
            expect($tester->getStatusCode())->toBe(0);
        });

        it('PSR-4 autoloader loads matching class from filesystem', function (Filesystem $execFs) {
            $tmpDir = sys_get_temp_dir();
            $ns = 'PhpSpecAutoloadTest' . uniqid() . '\\';
            $className = 'TestClass';
            $file = $tmpDir . '/' . $className . '.php';
            file_put_contents($file, '<?php namespace ' . rtrim($ns, '\\') . '; class ' . $className . ' {}');

            allow($execFs->exists())->toReturnUsing(fn($p) => str_ends_with($p, 'phpspec.json'));
            allow($execFs->read())->toReturn(json_encode(['autoload' => [$ns => $tmpDir . '/']]));
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute([]);

            $fqcn = $ns . $className;
            $loaded = class_exists($fqcn, true);
            unlink($file);
            expect($loaded)->toBeTrue();
        });

        it('PSR-4 autoloader skips non-matching prefix', function (Filesystem $execFs) {
            allow($execFs->exists())->toReturnUsing(fn($p) => str_ends_with($p, 'phpspec.json'));
            allow($execFs->read())->toReturn(json_encode(['autoload' => ['SpecificPrefix\\' => '/tmp/']]));
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute([]);

            $loaded = class_exists('WrongPrefix\\SomeClass', true);
            expect($loaded)->toBeFalse();
        });

        it('uses config format when CLI format is pretty', function (Filesystem $execFs) {
            allow($execFs->exists())->toReturnUsing(fn($p) => str_ends_with($p, 'phpspec.json'));
            allow($execFs->read())->toReturn(json_encode(['format' => 'dot']));
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute([]);
            $output = $tester->getDisplay();
            expect($output)->toContain('0 specs');
        });

        it('runs with random order without explicit seed', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $tester->execute(['--order' => 'random']);
            $output = $tester->getDisplay();
            expect($output)->toContain('Randomised with seed');
        });

        it('returns 0 for passing suite', function (Filesystem $execFs) {
            $config = new Configuration('.', $execFs);
            $cmd = new Run(new Loader($execFs), new Runner(), $config);

            $tester = new \Symfony\Component\Console\Tester\CommandTester($cmd);
            $exitCode = $tester->execute([]);
            expect($exitCode)->toBe(0);
        });
    });

});
