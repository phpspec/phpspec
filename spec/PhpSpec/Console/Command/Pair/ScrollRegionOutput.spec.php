<?php

use PhpSpec\Console\Command\Pair\InputArea;
use PhpSpec\Console\Command\Pair\RoleState;
use PhpSpec\Console\Command\Pair\ScrollRegionOutput;
use PhpSpec\Console\Command\Pair\StatusBar;
use Symfony\Component\Console\Output\BufferedOutput;

describe(ScrollRegionOutput::class, function () {
    this('inner', fn() => new BufferedOutput());
    // scrollBottom=20, inputRow=23, dividerRow=22, width=40
    this('output', fn() => new ScrollRegionOutput($this->inner, 20, 23, 22, 40));

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

    it('echoes each line of a multi-line input behind the quote bar', function () {
        $this->output->echoInput("one\ntwo");

        expect($this->inner->fetch())->toContain("  \u{2502} one" . PHP_EOL . "  \u{2502} two");
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

    context('an input that takes more than one row', function () {
        // scrollBottom=19, input=21, topDivider=20, width=40, bottomDivider=22, statusRow=23, roleRow=24
        this('framed', fn() => new ScrollRegionOutput($this->inner, 19, 21, 20, 40, 22, 23, 24, new StatusBar('~/proj', true, 'google', new RoleState())));

        it('grows upwards: the content scrolls up into the rows above and the top divider rises with it', function () {
            $area = $this->framed->resizeInput(3);
            $raw = $this->inner->fetch();

            expect($area)->toBeLike(new InputArea(19, 3));
            expect($raw)->toContain("\033[19;1H\n\n");
            expect($raw)->toContain("\033[1;17r");
            expect($raw)->toContain("\033[18;1H\033[K\033[36m\u{2500}");
        });

        it('gives the rows back as it shrinks: the content scrolls down into them', function () {
            $this->framed->resizeInput(3);
            $this->inner->fetch();

            expect($this->framed->resizeInput(1))->toBeLike(new InputArea(21, 1));
            $raw = $this->inner->fetch();
            expect($raw)->toContain("\033[1;19r\033[1;1H\033M\033M");
            expect($raw)->toContain("\033[20;1H\033[K\033[36m\u{2500}");
        });

        it('gives the rows back without erasing the content scrolled down into them', function () {
            $this->framed->resizeInput(3);
            $this->inner->fetch();

            $this->framed->resizeInput(1);
            $raw = $this->inner->fetch();

            expect($raw)->not()->toContain("\033[18;1H\033[K");
            expect($raw)->not()->toContain("\033[19;1H\033[K");
        });

        it('puts the top divider back in its place when the input collapses into the content', function () {
            $this->framed->resizeInput(3);
            $this->inner->fetch();

            $this->framed->returnToContent();

            expect($this->inner->fetch())->toContain("\033[20;1H\033[K\033[36m\u{2500}");
        });

        it('takes at most half the content rows', function () {
            expect($this->framed->resizeInput(30))->toBeLike(new InputArea(13, 9));
        });

        it('writes content above the area while it is grown', function () {
            $this->framed->resizeInput(3);
            $this->inner->fetch();

            $this->framed->write('meanwhile');
            expect($this->inner->fetch())->toContain("\033[17;1Hmeanwhile");
        });

        it('collapses the area when the cursor returns to the content', function () {
            $this->framed->resizeInput(3);
            $this->framed->returnToContent();
            $this->inner->fetch();

            $this->framed->writeln('after');
            expect($this->inner->fetch())->toContain("\033[19;1Hafter");
        });
    });

    it('draws the full bottom chrome when a status bar is configured', function () {
        $bar = new StatusBar('~/proj', true, 'google', new RoleState());
        // inner, scrollBottom=19, input=21, topDivider=20, width=40,
        // bottomDivider=22, statusRow=23, roleRow=24
        $output = new ScrollRegionOutput($this->inner, 19, 21, 20, 40, 22, 23, 24, $bar);

        $output->prepareForInput();
        $raw = $this->inner->fetch();

        expect($raw)->toContain("\033[20;1H"); // top divider
        expect($raw)->toContain("\033[22;1H"); // bottom divider
        expect($raw)->toContain("\033[23;1H"); // status row
        expect($raw)->toContain("\033[24;1H"); // role row
        expect($raw)->toContain("\033[21;1H"); // input row
        expect($raw)->toContain('ai: on');
        expect($raw)->toContain('provider: google');
        expect($raw)->toContain('navigator');
    });
});
