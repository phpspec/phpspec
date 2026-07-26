<?php

use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Chooser::class, function () {

    beforeEach(function () {
        $this->buffer = new BufferedOutput();
        $this->output = new PairOutput($this->buffer);
        $this->answers = [];
        $this->reads = 0;
        $this->reader = function () {
            $this->reads++;
            return array_shift($this->answers) ?? '';
        };
    });

    it('renders the question with three numbered options', function () {
        $this->answers = ['1'];
        $chooser = new Chooser($this->output, true, $this->reader);

        $chooser->choose('Do you want me to create class App\Wanted for you?', 'create-class', 'create classes');

        $text = $this->buffer->fetch();
        expect($text)->toContain('Do you want me to create class');
        expect($text)->toContain('1. Yes');
        expect($text)->toContain("2. Yes, and don't ask again — always create classes");
        expect($text)->toContain('3. No');
    });

    it('accepts on 1, empty input and y', function () {
        $chooser = new Chooser($this->output, true, $this->reader);

        $this->answers = ['1'];
        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeTrue();
        $this->answers = [''];
        expect($chooser->choose('Generate?', 'k2', 'do it'))->toBeTrue();
        $this->answers = ['y'];
        expect($chooser->choose('Generate?', 'k3', 'do it'))->toBeTrue();
    });

    it('declines on 3 and n', function () {
        $chooser = new Chooser($this->output, true, $this->reader);

        $this->answers = ['3'];
        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeFalse();
        $this->answers = ['n'];
        expect($chooser->choose('Generate?', 'k2', 'do it'))->toBeFalse();
    });

    it('remembers always per question kind and stops asking', function () {
        $chooser = new Chooser($this->output, true, $this->reader);

        $this->answers = ['2'];
        expect($chooser->choose('Create class App\First?', 'create-class', 'create classes'))->toBeTrue();
        expect($this->reads)->toBe(1);

        expect($chooser->choose('Create class App\Second?', 'create-class', 'create classes'))->toBeTrue();
        expect($this->reads)->toBe(1);
        expect($this->buffer->fetch())->not()->toContain('App\Second');
    });

    it('still asks for a different question kind after always', function () {
        $chooser = new Chooser($this->output, true, $this->reader);

        $this->answers = ['2', '3'];
        $chooser->choose('Create class?', 'create-class', 'create classes');

        expect($chooser->choose('Run specs now?', 'run-specs', 'run specs'))->toBeFalse();
        expect($this->reads)->toBe(2);
    });

    it('selects instantly on a single key without enter', function () {
        $keys = ['3'];
        $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
            return array_shift($keys) ?? "\n";
        });

        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeFalse();
        expect($keys)->toBe([]);
        expect($this->buffer->fetch())->toContain('▸ 1. Yes');
    });

    it('selects yes with y and always with a instantly', function () {
        $keys = ['y', 'a'];
        $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
            return array_shift($keys) ?? "\n";
        });

        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeTrue();
        expect($chooser->choose('Generate?', 'k2', 'do it'))->toBeTrue();
        expect($chooser->hasAlways('k2'))->toBeTrue();
    });

    it('moves the selection with arrow keys and confirms with enter', function () {
        $keys = ["\033[B", "\033[B", "\n"];
        $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
            return array_shift($keys) ?? "\n";
        });

        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeFalse();
        expect($this->buffer->fetch())->toContain('▸ 3. No');
    });

    it('clamps arrow movement at the first and last option', function () {
        $keys = ["\033[A", "\n"];
        $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
            return array_shift($keys) ?? "\n";
        });

        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeTrue();

        $keys = ["\033[B", "\033[B", "\033[B", "\033[B", "\n"];
        expect($chooser->choose('Generate?', 'k2', 'do it'))->toBeFalse();
    });

    it('treats escape as no', function () {
        $keys = ["\033"];
        $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
            return array_shift($keys) ?? "\n";
        });

        expect($chooser->choose('Generate?', 'k', 'do it'))->toBeFalse();
    });

    it('exposes whether a kind was answered always', function () {
        $chooser = new Chooser($this->output, true, $this->reader);

        expect($chooser->hasAlways('create-class'))->toBeFalse();

        $this->answers = ['2'];
        $chooser->choose('Create class?', 'create-class', 'create classes');

        expect($chooser->hasAlways('create-class'))->toBeTrue();
        expect($chooser->hasAlways('run-specs'))->toBeFalse();
    });

    it('prints the question and auto-accepts when not interactive', function () {
        $chooser = new Chooser($this->output, false, $this->reader);

        expect($chooser->choose('Create class App\Auto?', 'create-class', 'create classes'))->toBeTrue();
        expect($this->reads)->toBe(0);
        expect($this->buffer->fetch())->toContain('Create class App\Auto?');
    });

    context('Tab annotates the answer with a note', function () {
        it('captures a note after Tab on Yes and exposes it', function () {
            $keys = ["\t", 'r', 'e', 'n', 'a', 'm', 'e', "\n"];
            $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
                return array_shift($keys) ?? "\n";
            });

            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeTrue();
            expect($chooser->lastNote())->toBe('rename');
            expect($this->buffer->fetch())->toContain('1. Yes, rename');
        });

        it('captures a note after Tab on No', function () {
            $keys = ["\033[B", "\033[B", "\t", 'u', 's', 'e', ' ', 'X', "\n"];
            $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
                return array_shift($keys) ?? "\n";
            });

            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeFalse();
            expect($chooser->lastNote())->toBe('use X');
            expect($this->buffer->fetch())->toContain('3. No, use X');
        });

        it('edits the note with backspace', function () {
            $keys = ["\t", 'a', 'b', "\x7f", "\n"];
            $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
                return array_shift($keys) ?? "\n";
            });

            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeTrue();
            expect($chooser->lastNote())->toBe('a');
        });

        it('cancels the note on escape but keeps the selection', function () {
            $keys = ["\t", 'a', "\033", "\n"];
            $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
                return array_shift($keys) ?? "\n";
            });

            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeTrue();
            expect($chooser->lastNote())->toBe('');
        });

        it('ignores Tab on the always option', function () {
            $keys = ["\033[B", "\t", "\n"];
            $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
                return array_shift($keys) ?? "\n";
            });

            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeTrue();
            expect($chooser->hasAlways('k'))->toBeTrue();
            expect($chooser->lastNote())->toBe('');
        });

        it('resets the note between questions', function () {
            $keys = ["\t", 'x', "\n", "\n"];
            $chooser = new Chooser($this->output, true, null, function () use (&$keys) {
                return array_shift($keys) ?? "\n";
            });

            $chooser->choose('First?', 'k', 'do it');
            expect($chooser->lastNote())->toBe('x');

            $chooser->choose('Second?', 'k2', 'do it');
            expect($chooser->lastNote())->toBe('');
        });

        it('reads a note after a comma in line mode', function () {
            $chooser = new Chooser($this->output, true, $this->reader);

            $this->answers = ['1, rename it to tasks'];
            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeTrue();
            expect($chooser->lastNote())->toBe('rename it to tasks');
        });

        it('reads a decline note after a comma in line mode', function () {
            $chooser = new Chooser($this->output, true, $this->reader);

            $this->answers = ['n, make it a Feature instead'];
            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeFalse();
            expect($chooser->lastNote())->toBe('make it a Feature instead');
        });

        it('leaves the note empty for a plain line answer', function () {
            $chooser = new Chooser($this->output, true, $this->reader);

            $this->answers = ['3'];
            expect($chooser->choose('Apply?', 'k', 'do it'))->toBeFalse();
            expect($chooser->lastNote())->toBe('');
        });
    });
});
