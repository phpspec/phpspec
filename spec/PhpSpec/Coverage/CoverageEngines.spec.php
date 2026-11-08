<?php

use PhpSpec\Coverage\CoverageAdapter;
use PhpSpec\Coverage\CoverageEngines;
use PhpSpec\Coverage\CoverageRefusal;
use PhpSpec\Coverage\PhpRuntime;

describe(CoverageEngines::class, function () {

    $runtime = function (PhpRuntime $php, array $loaded = [], array $enabled = [], string $pcovDirectory = 'src', array $untraced = []): void {
        allow($php->loaded(any()))->toReturnUsing(fn(CoverageAdapter $adapter) => in_array($adapter, $loaded, true));
        allow($php->enabled(any()))->toReturnUsing(fn(CoverageAdapter $adapter) => in_array($adapter, $enabled, true));
        allow($php->pcovDirectory())->toReturn($pcovDirectory);
        allow($php->traces(any()))->toReturnUsing(fn(string $directory) => !in_array($directory, $untraced, true));
        allow($php->ini(any()))->toReturn('');
    };

    context('in this process', function () use ($runtime) {

        it('collects with PCOV when nothing is named and PCOV is enabled', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug], enabled: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug]);

            expect((new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']))->toBe(CoverageAdapter::Pcov);
        });

        it('collects with Xdebug when nothing is named and only Xdebug is enabled', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug], enabled: [CoverageAdapter::Xdebug]);

            expect((new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']))->toBe(CoverageAdapter::Xdebug);
        });

        it('collects with the engine the config names, whatever else is enabled', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug], enabled: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug]);

            expect((new CoverageEngines(CoverageAdapter::Xdebug, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']))->toBe(CoverageAdapter::Xdebug);
        });

        it('refuses naming both engines when nothing is named and neither is enabled, with the command under the loaded one', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Xdebug]);

            $refusal = (new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']);

            expect($refusal)->toBeAnInstanceOf(CoverageRefusal::class);
            expect($refusal->reason)->toBe('Code coverage requires PCOV or Xdebug in coverage mode');
            expect($refusal->remedy)->toBe('XDEBUG_MODE=coverage vendor/bin/phpspec run --coverage');
        });

        it('prefers the command under PCOV when both are loaded and neither is enabled', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug]);

            expect((new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src'])->remedy)->toBe('php -d pcov.enabled=1 vendor/bin/phpspec run --coverage');
        });

        it('has no command to offer when neither engine is loaded', function (PhpRuntime $php) use ($runtime) {
            $runtime($php);

            $refusal = (new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']);

            expect($refusal->reason)->toBe('Code coverage requires PCOV or Xdebug in coverage mode');
            expect($refusal->remedy)->toBeNull();
        });

        it('refuses for the named engine when it is loaded but not enabled, with the command under it', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug], enabled: [CoverageAdapter::Xdebug]);

            $refusal = (new CoverageEngines(CoverageAdapter::Pcov, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']);

            expect($refusal->reason)->toBe('Code coverage requires PCOV enabled');
            expect($refusal->remedy)->toBe('php -d pcov.enabled=1 vendor/bin/phpspec run --coverage');
        });

        it('refuses for the named engine with no command when it is not loaded', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov], enabled: [CoverageAdapter::Pcov]);

            $refusal = (new CoverageEngines(CoverageAdapter::Xdebug, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['src']);

            expect($refusal->reason)->toBe('Code coverage requires Xdebug with coverage mode enabled');
            expect($refusal->remedy)->toBeNull();
        });

        it('refuses PCOV when a directory the run reports on is outside what PCOV traces, naming the ways past it', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug], enabled: [CoverageAdapter::Pcov], pcovDirectory: 'src', untraced: ['lib']);

            $refusal = (new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['lib']);

            expect($refusal->reason)->toBe('PCOV traces src, not lib');
            expect($refusal->remedy)->toBe('php -d pcov.enabled=1 -d pcov.directory=lib vendor/bin/phpspec run --coverage');
            expect($refusal->options)->toBe([
                'Set pcov.directory=lib in php.ini to trace it in every run.',
                'Set coverage_adapter: xdebug in the config to collect with Xdebug.',
            ]);
        });

        it('says how to run phpspec again under PCOV tracing what the run reports on, when it knows the command it was started by', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov], enabled: [CoverageAdapter::Pcov], pcovDirectory: 'src', untraced: ['lib']);

            $started = new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage', ['vendor/bin/phpspec', 'run', '--coverage']);
            $unknown = new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage');

            expect($started->inThisProcess(['./src', 'lib'])->restart)->toBe(['-d', 'pcov.enabled=1', '-d', 'pcov.directory=.', 'vendor/bin/phpspec', 'run', '--coverage']);
            expect($unknown->inThisProcess(['./src', 'lib'])->restart)->toBe([]);
        });

        it('offers Xdebug as a way past only when Xdebug is loaded', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov], enabled: [CoverageAdapter::Pcov], untraced: ['lib']);

            expect((new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['lib'])->options)->toBe([
                'Set pcov.directory=lib in php.ini to trace it in every run.',
            ]);
        });

        it('names the directory that holds every reported one when several are outside', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov], enabled: [CoverageAdapter::Pcov], untraced: ['lib/one', 'lib/two']);

            $refusal = (new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['lib/one', 'lib/two']);

            expect($refusal->reason)->toBe('PCOV traces src, not lib/one or lib/two');
            expect($refusal->remedy)->toBe('php -d pcov.enabled=1 -d pcov.directory=lib vendor/bin/phpspec run --coverage');
        });

        it('names the project root when the reported directories share nothing closer', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov], enabled: [CoverageAdapter::Pcov], untraced: ['lib']);

            expect((new CoverageEngines(null, $php, 'vendor/bin/phpspec run --coverage'))->inThisProcess(['./src', 'lib'])->remedy)->toBe('php -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpspec run --coverage');
        });

    });

    context('for the processes it starts', function () use ($runtime) {

        it('names the engine a child collects with: the named one, else the first loaded', function (PhpRuntime $php) use ($runtime) {
            $runtime($php, loaded: [CoverageAdapter::Pcov, CoverageAdapter::Xdebug]);

            expect((new CoverageEngines(CoverageAdapter::Xdebug, $php, ''))->forChildren())->toBe(CoverageAdapter::Xdebug);
            expect((new CoverageEngines(null, $php, ''))->forChildren())->toBe(CoverageAdapter::Pcov);
        });

        it('names no engine for a child when none is loaded', function (PhpRuntime $php) use ($runtime) {
            $runtime($php);

            expect((new CoverageEngines(null, $php, ''))->forChildren())->toBeNull();
        });

        it('switches both engines off in a child that collects nothing', function (PhpRuntime $php) use ($runtime) {
            $runtime($php);

            expect((new CoverageEngines(null, $php, ''))->switches(null))->toBe(['-d', 'pcov.enabled=0', '-d', 'xdebug.mode=off']);
        });

        it('switches the engine on, and the other off, in a child that collects', function (PhpRuntime $php) use ($runtime) {
            $runtime($php);

            expect((new CoverageEngines(null, $php, ''))->switches(CoverageAdapter::Xdebug))->toBe(['-d', 'xdebug.mode=coverage', '-d', 'pcov.enabled=0']);
        });

        it('carries the PCOV directory and exclusions this process was given to a child collecting with PCOV', function (PhpRuntime $php) {
            allow($php->ini('pcov.directory'))->toReturn('lib');
            allow($php->ini('pcov.exclude'))->toReturn('~vendor~');

            expect((new CoverageEngines(null, $php, ''))->switches(CoverageAdapter::Pcov))->toBe([
                '-d', 'pcov.enabled=1', '-d', 'xdebug.mode=off', '-d', 'pcov.directory=lib', '-d', 'pcov.exclude=~vendor~',
            ]);
        });

    });

});
