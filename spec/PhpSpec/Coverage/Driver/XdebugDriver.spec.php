<?php

use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\Driver\XdebugDriver;
use PhpSpec\Coverage\LivePhpRuntime;

describe(XdebugDriver::class, function () {

    it('is a coverage driver', function () {
        expect(new XdebugDriver())->toBeAnInstanceOf(CoverageDriver::class);
    });

    // Cycling xdebug start/stop would clobber a live collection (xdebug
    // coverage state is process-global), so skip these when one is running.
    if ((new LivePhpRuntime())->enabled(CoverageAdapter::Xdebug) && CoverageRegistry::collector() === null) {

        it('collects line hits between start and stop', function () {
            $driver = new XdebugDriver();

            $driver->start();
            $probe = require __DIR__ . '/fixtures/covered_probe.php';
            $data = $driver->stop();

            expect($probe)->toBe('covered');
            expect($data)->toHaveKey(realpath(__DIR__ . '/fixtures/covered_probe.php'));
        });

        // From Xdebug 3.6 a cycle also lists the unused-line analysis of every
        // file compiled before it began; execution counts are what start afresh.
        it('starts a fresh cycle each time', function () {
            $driver = new XdebugDriver();

            $driver->start();
            $driver->stop();
            $driver->start();
            $data = $driver->stop();

            $probe = $data[realpath(__DIR__ . '/fixtures/covered_probe.php')] ?? [];
            expect(array_filter($probe, fn (int $hit) => $hit > 0))->toBe([]);
        });

        it('records only the files under the directories it traces, and every file when it traces none', function () {
            $dir = sys_get_temp_dir() . '/phpspec_traced_' . uniqid();
            mkdir($dir . '/src', 0777, true);
            mkdir($dir . '/lib', 0777, true);
            file_put_contents($dir . '/src/In.php', "<?php\n\nreturn 'in';\n");
            file_put_contents($dir . '/lib/Out.php', "<?php\n\nreturn 'out';\n");

            try {
                $traced = new XdebugDriver([$dir . '/src']);
                $traced->start();
                require $dir . '/src/In.php';
                require $dir . '/lib/Out.php';
                $tracedData = $traced->stop();

                $everything = new XdebugDriver();
                $everything->start();
                require $dir . '/lib/Out.php';
                $everyData = $everything->stop();
            } finally {
                array_map('unlink', [$dir . '/src/In.php', $dir . '/lib/Out.php']);
                array_map('rmdir', [$dir . '/src', $dir . '/lib', $dir]);
            }

            expect(array_map('basename', array_keys($tracedData)))->toContain('In.php');
            expect(array_map('basename', array_keys($tracedData)))->not()->toContain('Out.php');
            expect(array_map('basename', array_keys($everyData)))->toContain('Out.php');
        });

        it('keeps what it recorded across a pause, for the next stop', function () {
            $driver = new XdebugDriver();

            $driver->start();
            require __DIR__ . '/fixtures/covered_probe.php';
            $driver->pause();
            $data = $driver->stop();

            expect($data[realpath(__DIR__ . '/fixtures/covered_probe.php')] ?? [])->toContain(1);
        });

        it('records nothing while paused', function () {
            $driver = new XdebugDriver();

            $driver->start();
            $driver->pause();
            require __DIR__ . '/fixtures/covered_probe.php';
            $data = $driver->stop();

            $probe = $data[realpath(__DIR__ . '/fixtures/covered_probe.php')] ?? [];
            expect(array_filter($probe, fn (int $hit) => $hit > 0))->toBe([]);
        });

    }

});
