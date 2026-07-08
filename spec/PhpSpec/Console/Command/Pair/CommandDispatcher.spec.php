<?php

use PhpSpec\CodeGeneration\ClassGenerator;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Pair\CommandDispatcher;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Filesystem;
use PhpSpec\Loader;
use PhpSpec\Runner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\BufferedOutput;

describe(CommandDispatcher::class, function () {

    beforeEach(function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->isFile())->toReturn(false);
        allow($fs->isDir())->toReturn(false);
    });

    let('buffer', fn() => new BufferedOutput());
    let('pairOutput', fn() => new PairOutput($this->buffer));
    let('config', fn(Filesystem $fs) => new Configuration('.', $fs));
    let('dispatcher', fn(Filesystem $fs) => new CommandDispatcher(
        new Loader($fs),
        new Runner(),
        new SpecGenerator('spec', $fs),
        new ClassGenerator('src', $fs),
        $this->config,
        $this->pairOutput,
        false,
        $fs,
    ));

    it('instantiates', fn() => expect($this->dispatcher)->toBeAnInstanceOf(CommandDispatcher::class));

    it('normalises slash paths to namespaced classes in describe', function (Filesystem $fs) {
        $written = [];
        allow($fs->write())->toReturnUsing(function (string $path, string $content) use (&$written) {
            $written[$path] = $content;
        });

        $this->dispatcher->dispatch('describe App/Basket');

        $classContents = implode("\n", array_filter(
            $written,
            fn(string $path) => str_ends_with(str_replace('\\', '/', $path), 'src/App/Basket.php'),
            ARRAY_FILTER_USE_KEY,
        ));
        expect($classContents)->toContain('namespace App;');
        expect($classContents)->toContain('class Basket');
        expect($classContents)->not()->toContain('class App/Basket');
    });

    it('returns CONTINUE for /help', function () {
        expect($this->dispatcher->dispatch('/help'))->toBe(CommandDispatcher::CONTINUE);
    });

    it('shows help text for /help', function () {
        $this->dispatcher->dispatch('/help');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Available commands');
    });

    it('returns QUIT for /quit', function () {
        expect($this->dispatcher->dispatch('/quit'))->toBe(CommandDispatcher::QUIT);
    });

    it('returns QUIT for /exit', function () {
        expect($this->dispatcher->dispatch('/exit'))->toBe(CommandDispatcher::QUIT);
    });

    it('returns CONTINUE for empty input', function () {
        expect($this->dispatcher->dispatch(''))->toBe(CommandDispatcher::CONTINUE);
    });

    it('returns CONTINUE for unknown command', function () {
        expect($this->dispatcher->dispatch('foobar'))->toBe(CommandDispatcher::CONTINUE);
    });

    it('shows error for unknown command', function () {
        $this->dispatcher->dispatch('foobar');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Unknown command: foobar');
    });

    it('returns CONTINUE for clear command', function () {
        expect($this->dispatcher->dispatch('clear'))->toBe(CommandDispatcher::CONTINUE);
    });

    it('shows error when describe is missing argument', function () {
        $this->dispatcher->dispatch('describe');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Usage: describe');
    });

    it('describe auto-generates spec and shows confirmation', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => match (true) {
            str_ends_with($p, '.spec.php') => false,
            default => false,
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $result = $this->dispatcher->dispatch('describe Acme\Greeter');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        $output = $this->buffer->fetch();
        expect($output)->toContain('Specification for');
        expect($output)->toContain('Acme\Greeter');
        expect($output)->toContain('.spec.php');
    });

    it('describe offers class creation after spec generation', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $this->dispatcher->dispatch('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Do you want me to create class');
    });

    it('describe shows generated class file', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => match (true) {
            str_ends_with($p, '.php') && !str_ends_with($p, '.spec.php') => false,
            default => false,
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $this->dispatcher->dispatch('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Do you want me to create class');
    });

    it('describe offers to run specs after generation', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);

        $this->dispatcher->dispatch('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Do you want to run specs now');
    });

    it('describe shows existing spec message when spec exists', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => str_ends_with($p, '.spec.php'));

        $this->dispatcher->dispatch('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Spec already exists');
    });

    it('describe displays spec file content after generation', function (Filesystem $fs) {
        $specCalls = 0;
        allow($fs->exists())->toReturnUsing(function (string $p) use (&$specCalls) {
            if (str_ends_with($p, '.spec.php')) {
                // First call from SpecGenerator: not exists. Second call from CommandDispatcher: exists.
                return $specCalls++ > 0;
            }
            return false;
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\n// spec content");

        $this->dispatcher->dispatch('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('[NEW FILE]');
        expect($output)->toContain('spec content');
    });

    it('describe displays class file content after generation', function (Filesystem $fs) {
        $specCalls = 0;
        $classCalls = 0;
        allow($fs->exists())->toReturnUsing(function (string $p) use (&$specCalls, &$classCalls) {
            if (str_ends_with($p, '.spec.php')) {
                return $specCalls++ > 0;
            }
            if (str_ends_with($p, '.php')) {
                // First call from ClassGenerator: not exists. Second from CommandDispatcher: exists.
                return $classCalls++ > 0;
            }
            return false;
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\nclass Greeter {}");

        $this->dispatcher->dispatch('describe Acme\Greeter');
        $output = $this->buffer->fetch();
        expect($output)->toContain('class Greeter');
    });

    it('shows error when exemplify is missing arguments', function () {
        $this->dispatcher->dispatch('exemplify');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Usage: exemplify');
    });

    it('shows error when exemplify is missing method argument', function () {
        $this->dispatcher->dispatch('exemplify Acme\Calculator');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Usage: exemplify');
    });

    it('exemplify adds example and shows confirmation', function (Filesystem $fs) {
        allow($fs->exists())->toReturnUsing(fn(string $p) => match (true) {
            str_ends_with($p, '.spec.php') => false,
            default => false,
        });
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n});");

        $result = $this->dispatcher->dispatch('exemplify Acme\Calculator add');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        $output = $this->buffer->fetch();
        expect($output)->toContain('Example for');
        expect($output)->toContain('Acme\Calculator::add');
    });

    it('exemplify generates spec when it does not exist', function (Filesystem $fs) {
        allow($fs->exists())->toReturn(false);
        allow($fs->mkdir())->toReturn(null);
        allow($fs->write())->toReturn(null);
        allow($fs->read())->toReturn("<?php\ndescribe(Calculator::class, function() {\n});");

        $this->dispatcher->dispatch('exemplify Acme\Calculator add');
        $output = $this->buffer->fetch();
        expect($output)->toContain('Specification for');
    });

    it('runs specs with run command', function () {
        $result = $this->dispatcher->dispatch('run');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
        $output = $this->buffer->fetch();
        expect($output)->toContain('0 specs');
    });

    it('runs specs at specific path with run command', function () {
        $result = $this->dispatcher->dispatch('run spec/NonExistent');
        expect($result)->toBe(CommandDispatcher::CONTINUE);
    });

    context('smart command routing without AI', function () {
        it('falls through to run command for multi-word argument without AI', function () {
            $result = $this->dispatcher->dispatch('run the scenarios and fix them');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            $output = $this->buffer->fetch();
            // Without AI, shouldRouteToAi returns false, so it goes to handleRun
            expect($output)->not()->toContain('Unknown command');
        });

        it('routes single-word run argument as run command', function () {
            $result = $this->dispatcher->dispatch('run spec/App');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
        });

        it('routes unrecognized input to AI fallback', function () {
            $this->dispatcher->dispatch('hello world');
            $output = $this->buffer->fetch();
            // Without AI, handleAi falls back to handleUnknown with config hint
            expect($output)->toContain('Unknown command');
            expect($output)->toContain('configure an AI provider');
        });

        it('routes single-word describe argument as describe command', function (Filesystem $fs) {
            allow($fs->exists())->toReturn(false);
            allow($fs->mkdir())->toReturn(null);
            allow($fs->write())->toReturn(null);

            $this->dispatcher->dispatch('describe Acme\\Foo');
            $output = $this->buffer->fetch();
            expect($output)->not()->toContain('Unknown command');
        });
    });

    context('exceedsCommandArgLimit', function () {
        it('returns false for run with single argument', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('run', 'spec/App'))->toBeFalse();
        });

        it('returns true for run with multiple arguments', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('run', 'the scenarios and fix them'))->toBeTrue();
        });

        it('returns false for describe with single argument', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('describe', 'Acme\\Foo'))->toBeFalse();
        });

        it('returns true for describe with multiple arguments', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('describe', 'a calculator class'))->toBeTrue();
        });

        it('returns false for exemplify with two arguments', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('exemplify', 'Acme\\Foo bar'))->toBeFalse();
        });

        it('returns true for exemplify with more than two arguments', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('exemplify', 'a class that does things'))->toBeTrue();
        });

        it('returns false for unknown command', function () {
            expect(CommandDispatcher::exceedsCommandArgLimit('unknown', 'some args here'))->toBeFalse();
        });
    });

    context('help output', function () {
        it('shows AI as not configured when no ai config', function () {
            $this->dispatcher->dispatch('/help');
            $output = $this->buffer->fetch();
            expect($output)->toContain('not configured');
        });
    });

    context('logging', function () {
        it('creates log file on dispatch', function () {
            $logFile = getcwd() . '/.phpspec/pair.log';
            if (file_exists($logFile)) {
                unlink($logFile);
            }

            $this->dispatcher->dispatch('/help');

            expect(file_exists($logFile))->toBeTrue();
            $contents = file_get_contents($logFile);
            expect($contents)->toContain('[CMD]');
            expect($contents)->toContain('/help');

            unlink($logFile);
        });
    });

    context('delegated application commands', function () {
        let('app', function () {
            $app = new Application('phpspec', '1.0');
            $app->setAutoExit(false);
            $app->{method_exists($app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Next(new Configuration('.')));
            $app->{method_exists($app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Refactor(new Configuration('.')));
            return $app;
        });
        let('appDispatcher', fn(Filesystem $fs) => new CommandDispatcher(
            new Loader($fs),
            new Runner(),
            new SpecGenerator('spec', $fs),
            new ClassGenerator('src', $fs),
            $this->config,
            $this->pairOutput,
            false,
            $fs,
            $this->app,
        ));

        it('delegates next command and returns CONTINUE', function () {
            $result = $this->appDispatcher->dispatch('next');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
        });

        it('delegates refactor with argument', function () {
            $result = $this->appDispatcher->dispatch('refactor App\\Calculator');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
        });

        it('routes next with natural language to unknown when no AI', function () {
            $this->appDispatcher->dispatch('next what should I build');
            $output = $this->buffer->fetch();
            expect($output)->toContain('Unknown command');
        });

        it('shows additional commands in help', function () {
            $this->appDispatcher->dispatch('/help');
            $output = $this->buffer->fetch();
            expect($output)->toContain('Additional commands');
            expect($output)->toContain('next');
            expect($output)->toContain('refactor');
        });

        it('does not list pair in additional commands', function () {
            $this->app->{method_exists($this->app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Pair(
                new Loader(),
                new Runner(),
                new SpecGenerator('spec'),
                new ClassGenerator('src'),
                new Configuration('.'),
            ));
            $this->appDispatcher->dispatch('/help');
            $output = $this->buffer->fetch();
            // pair should not appear in additional commands
            expect($output)->not()->toMatch('/Additional commands.*pair/s');
        });

        it('silently ignores pair command input', function () {
            $this->app->{method_exists($this->app, 'addCommand') ? 'addCommand' : 'add'}(new \PhpSpec\Console\Command\Pair(
                new Loader(),
                new Runner(),
                new SpecGenerator('spec'),
                new ClassGenerator('src'),
                new Configuration('.'),
            ));
            $result = $this->appDispatcher->dispatch('pair');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            $output = $this->buffer->fetch();
            expect($output)->not()->toContain('Unknown command');
        });

        it('shows error when delegated command fails', function () {
            // refactor without required arg triggers an exception in bind
            $result = $this->appDispatcher->dispatch('refactor');
            expect($result)->toBe(CommandDispatcher::CONTINUE);
            $output = $this->buffer->fetch();
            expect($output)->toContain('Not enough arguments');
        });
    });
});
