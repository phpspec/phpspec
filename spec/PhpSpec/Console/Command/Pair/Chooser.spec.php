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
});
