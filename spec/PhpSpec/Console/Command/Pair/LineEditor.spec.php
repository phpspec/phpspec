<?php

use PhpSpec\Console\Command\Pair\LineEditor;
use PhpSpec\Console\Command\Pair\PairOutput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(LineEditor::class, function () {

    this('buffer', fn() => new BufferedOutput());
    this('pairOutput', fn() => new PairOutput($this->buffer));

    // Builds a LineEditor whose key reader replays a fixed token sequence.
    $keying = function (PairOutput $output, array $seq): LineEditor {
        return new LineEditor($output, null, function () use (&$seq) {
            return array_shift($seq) ?? "\n";
        });
    };

    it('returns the typed characters on Enter', function () use ($keying) {
        $editor = $keying($this->pairOutput, ['h', 'i', "\n"]);

        expect($editor->readLine('❯ '))->toBe('hi');
    });

    it('accepts the suggestion with the Right arrow', function () use ($keying) {
        $editor = $keying($this->pairOutput, ["\033[C", "\n"]);

        expect($editor->readLine('❯ ', '/run spec/App/Todo.spec.php'))->toBe('/run spec/App/Todo.spec.php');
    });

    it('accepts the suggestion with Tab', function () use ($keying) {
        $editor = $keying($this->pairOutput, ["\t", "\n"]);

        expect($editor->readLine('❯ ', '/next'))->toBe('/next');
    });

    it('lets typing dismiss the suggestion', function () use ($keying) {
        $editor = $keying($this->pairOutput, ['x', "\n"]);

        expect($editor->readLine('❯ ', '/next'))->toBe('x');
    });

    it('deletes the last character on backspace', function () use ($keying) {
        $editor = $keying($this->pairOutput, ['a', 'b', "\177", "\n"]);

        expect($editor->readLine('❯ '))->toBe('a');
    });

    it('returns null when cancelled with Ctrl-C', function () use ($keying) {
        $editor = $keying($this->pairOutput, ['a', "\003"]);

        expect($editor->readLine('❯ '))->toBeNull();
    });

    it('recalls the most recent line with the Up arrow', function () use ($keying) {
        $editor = $keying($this->pairOutput, ["\033[A", "\n"]);

        expect($editor->readLine('❯ ', null, ['/run', '/describe App/Todo']))->toBe('/describe App/Todo');
    });

    it('renders the suggestion dimmed', function () use ($keying) {
        $editor = $keying($this->pairOutput, ["\n"]);

        $editor->readLine('❯ ', '/next');

        expect($this->buffer->fetch())->toContain("\033[2m");
    });

    context('on a screen laid out for pair', function () use ($keying) {
        beforeEach(function () {
            putenv('COLUMNS=12');
            putenv('LINES=12');
            $this->pairOutput->setupLayout();
            $this->buffer->fetch();
        });

        afterEach(function () {
            putenv('COLUMNS');
            putenv('LINES');
        });

        it('keeps the line being typed on the bottom row of the input, moving the top divider up as the line wraps', function () use ($keying) {
            $editor = $keying($this->pairOutput, [...str_split('abcdefghijklm'), "\n"]);

            expect($editor->readLine('❯ '))->toBe('abcdefghijklm');
            $raw = $this->buffer->fetch();
            expect($raw)->toContain("\033[1;9r");
            expect($raw)->toContain("\033[10;1H\033[K\033[36m\u{2500}");
            expect($raw)->toContain("\033[11;1H\033[K❯ abcdefghij\033[12;1H\033[Kklm\033[12;4H");
        });
    });

    it('reads a whole line without a ghost when not a TTY', function () {
        $editor = new LineEditor($this->pairOutput, fn(): ?string => 'hello world');

        expect($editor->readLine('❯ ', '/next'))->toBe('hello world');
    });

});
