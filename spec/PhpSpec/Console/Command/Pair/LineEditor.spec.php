<?php

use PhpSpec\Console\Command\Pair\LineEditor;
use PhpSpec\Console\Command\Pair\PairOutput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(LineEditor::class, function () {

    let('buffer', fn() => new BufferedOutput());
    let('pairOutput', fn() => new PairOutput($this->buffer));

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

    it('reads a whole line without a ghost when not a TTY', function () {
        $editor = new LineEditor($this->pairOutput, fn(): ?string => 'hello world');

        expect($editor->readLine('❯ ', '/next'))->toBe('hello world');
    });

});
