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
