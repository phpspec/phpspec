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
            expect($raw)->toContain("\033[11;1H\033[K❯ abcdefghij\033[12;1H\033[K  klm\033[12;6H");
        });
    });

    context('editing the line', function () use ($keying) {
        it('inserts at the cursor, moved with the arrows, Home and End', function () use ($keying) {
            expect($keying($this->pairOutput, ['a', 'c', "\033[D", 'b', "\033[H", '>', "\033[F", '<', "\n"])->readLine('❯ '))->toBe('>abc<');
        });

        it('moves by a word with Option and the arrows', function () use ($keying) {
            expect($keying($this->pairOutput, [...str_split('say world'), "\033b", 'hello ', "\n"])->readLine('❯ '))->toBe('say hello world');
        });

        it('deletes under the cursor with Delete', function () use ($keying) {
            expect($keying($this->pairOutput, ['a', 'x', 'b', "\033[D", "\033[D", "\033[3~", "\n"])->readLine('❯ '))->toBe('ab');
        });

        it('adds a new line with Shift+Enter or Alt+Enter, and submits with Enter', function () use ($keying) {
            expect($keying($this->pairOutput, ['a', "\033[13;2u", 'b', "\033\r", 'c', "\r"])->readLine('❯ '))->toBe("a\nb\nc");
        });

        it('inserts a paste whole, its line breaks with it', function () use ($keying) {
            expect($keying($this->pairOutput, ["\033[200~one\ntwo\033[201~", "\n"])->readLine('❯ '))->toBe("one\ntwo");
        });

        it('moves up a line before it reaches the history, and recalls it from the first line', function () use ($keying) {
            $typed = $keying($this->pairOutput, ['a', "\033[13;2u", 'b', "\033[A", 'X', "\n"])->readLine('❯ ', null, ['/run']);
            expect($typed)->toBe("aX\nb");

            $recalled = $keying($this->pairOutput, ['a', "\033[13;2u", 'b', "\033[A", "\033[A", "\n"])->readLine('❯ ', null, ['/run']);
            expect($recalled)->toBe('/run');
        });

        it('comes back down from the history to what was being typed, and goes no further', function () use ($keying) {
            $typed = $keying($this->pairOutput, ['d', 'r', 'a', 'f', 't', "\033[A", "\033[B", "\033[B", "\n"])->readLine('❯ ', null, ['/run']);

            expect($typed)->toBe('draft');
        });

        it('ignores a key it does not know', function () use ($keying) {
            expect($keying($this->pairOutput, ['a', "\033[Z", 'b', "\n"])->readLine('❯ '))->toBe('ab');
        });
    });

    it('reads a whole line without a ghost when not a TTY', function () {
        $editor = new LineEditor($this->pairOutput, fn(): ?string => 'hello world');

        expect($editor->readLine('❯ ', '/next'))->toBe('hello world');
    });

});
