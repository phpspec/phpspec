<?php

use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(ScrollRegionOutput::class, function () {
    let('inner', fn() => new BufferedOutput());
    // scrollBottom=20, inputRow=23, dividerRow=22, width=40
    let('output', fn() => new ScrollRegionOutput($this->inner, 20, 23, 22, 40));

    it('delegates write to inner output', function () {
        $this->output->write('hello');
        expect($this->inner->fetch())->toContain('hello');
    });

    it('delegates writeln to inner output', function () {
        $this->output->writeln('world');
        expect($this->inner->fetch())->toContain('world');
    });

    it('emits cursor positioning on prepareForInput', function () {
        $this->output->prepareForInput();
        $raw = $this->inner->fetch();
        // Should contain escape to divider row 22 and input row 23
        expect($raw)->toContain("\033[22;1H");
        expect($raw)->toContain("\033[23;1H");
    });

    it('emits clear line escape on returnToContent', function () {
        $this->output->returnToContent();
        $raw = $this->inner->fetch();
        expect($raw)->toContain("\033[23;1H");
        expect($raw)->toContain("\033[K");
    });

    it('repositions cursor to scroll region after prepareForInput then write', function () {
        $this->output->prepareForInput();
        $this->inner->fetch(); // discard positioning output

        $this->output->write('after input');
        $raw = $this->inner->fetch();
        // Should reposition to scrollBottom row 20 before writing content
        expect($raw)->toContain("\033[20;1H");
        expect($raw)->toContain('after input');
    });

    it('delegates verbosity methods', function () {
        $this->output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);
        expect($this->output->getVerbosity())->toBe(BufferedOutput::VERBOSITY_VERBOSE);
        expect($this->output->isVerbose())->toBeTrue();
    });

    it('delegates decorated flag', function () {
        $this->output->setDecorated(true);
        expect($this->output->isDecorated())->toBeTrue();
    });

    it('delegates formatter', function () {
        $formatter = $this->output->getFormatter();
        expect($formatter)->toBeAnInstanceOf(\Symfony\Component\Console\Formatter\OutputFormatterInterface::class);
    });

    it('echoes input as a quoted line in the scroll region', function () {
        $this->output->echoInput('Y');
        $raw = $this->inner->fetch();
        expect($raw)->toContain("\u{2502}");
        expect($raw)->toContain('Y');
    });

    it('echoInput repositions after prepareForInput', function () {
        $this->output->prepareForInput();
        $this->inner->fetch(); // discard

        $this->output->echoInput('n');
        $raw = $this->inner->fetch();
        // Should reposition to scrollBottom then write
        expect($raw)->toContain("\033[20;1H");
        expect($raw)->toContain('n');
    });

    it('repositions cursor to scroll region after returnToContent then writeln', function () {
        $this->output->returnToContent();
        $this->inner->fetch(); // discard

        $this->output->writeln('after return');
        $raw = $this->inner->fetch();
        expect($raw)->toContain("\033[20;1H");
        expect($raw)->toContain('after return');
    });

    it('does not reposition when already in content', function () {
        // First write sets cursorInContent to true
        $this->output->write('first');
        $this->inner->fetch();

        // Second write should NOT emit cursor positioning
        $this->output->write('second');
        $raw = $this->inner->fetch();
        expect($raw)->toBe('second');
    });

    it('delegates isQuiet', function () {
        $this->output->setVerbosity(BufferedOutput::VERBOSITY_QUIET);
        expect($this->output->isQuiet())->toBeTrue();
    });

    it('delegates isVeryVerbose', function () {
        $this->output->setVerbosity(BufferedOutput::VERBOSITY_VERY_VERBOSE);
        expect($this->output->isVeryVerbose())->toBeTrue();
    });

    it('delegates isDebug', function () {
        $this->output->setVerbosity(BufferedOutput::VERBOSITY_DEBUG);
        expect($this->output->isDebug())->toBeTrue();
    });

    it('delegates setFormatter', function () {
        $formatter = $this->output->getFormatter();
        $this->output->setFormatter($formatter);
        expect($this->output->getFormatter())->toBe($formatter);
    });

    it('draws divider on prepareForInput', function () {
        $this->output->prepareForInput();
        $raw = $this->inner->fetch();
        expect($raw)->toContain("\u{2500}");
    });
});
