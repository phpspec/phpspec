<?php

use PhpSpec\Console\Command\Pair\Chooser;
use PhpSpec\Console\Command\Pair\PairOutput;
use PhpSpec\Console\Command\Run\Generation;
use PhpSpec\Console\Consent;
use PhpSpec\Console\Prompt;
use Symfony\Component\Console\Output\BufferedOutput;

describe(Consent::class, function () {

    let('output', fn() => new BufferedOutput());
    let('answering', fn() => function (Prompt $prompt, string ...$answers): Prompt {
        allow($prompt->ask(any()))->toReturnUsing(function () use (&$answers): ?string {
            return array_shift($answers);
        });

        return $prompt;
    });

    it('asks on a [Y/n] line, Enter and y saying yes, anything else no', function (Prompt $prompt) {
        $consent = new Consent('Run with --accept-offers to %s.', prompt: ($this->answering)($prompt, '', 'y', 'n'));

        expect($consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the steps'))->toBeTrue();
        expect($consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the steps'))->toBeTrue();
        expect($consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the steps'))->toBeFalse();
        expect($this->output->fetch())->toContain('  Apply this step? [Y/n]');
    });

    it('takes yes and no as words, whatever their case', function (Prompt $prompt) {
        $consent = new Consent('Run with --accept-offers to %s.', prompt: ($this->answering)($prompt, 'yes', 'YES', 'no', 'maybe'));

        expect($consent->confirm($this->output, '  Apply?', 'k', 'apply'))->toBeTrue();
        expect($consent->confirm($this->output, '  Apply?', 'k', 'apply'))->toBeTrue();
        expect($consent->confirm($this->output, '  Apply?', 'k', 'apply'))->toBeFalse();
        expect($consent->confirm($this->output, '  Apply?', 'k', 'apply'))->toBeFalse();
    });

    it('numbers the answers when a no has a consequence to say', function (Prompt $prompt) {
        $consent = new Consent('Run with --no-interaction to %s.', prompt: ($this->answering)($prompt, '2', '1', ''));
        $undoes = 'this will undo all changes until the last green state';

        expect($consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the steps', $undoes))->toBeFalse();
        expect($consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the steps', $undoes))->toBeTrue();
        expect($consent->confirm($this->output, '  Apply this step?', 'refactor-step', 'apply the steps', $undoes))->toBeTrue();
        expect(str_replace(PHP_EOL, "\n", $this->output->fetch()))->toContain("  Apply this step?\n    1. Yes\n    2. No (this will undo all changes until the last green state)\n");
    });

    it('tells nobody answering apart from a no, for the caller to decide what nobody means', function (Prompt $prompt) {
        $consent = new Consent('Run with --accept-offers to %s.', prompt: ($this->answering)($prompt, 'n'));

        expect($consent->decide($this->output, '  Apply?', 'k', 'apply'))->toBeFalse();
        expect($consent->decide($this->output, '  Apply?', 'k', 'apply'))->toBeNull();
        expect($this->output->fetch())->not()->toContain('nobody to answer');
    });

    it('says nothing was written, and how to have it anyway, when nobody answers', function (Prompt $prompt) {
        $consent = new Consent('Run phpspec refactor in a terminal to %s.', prompt: ($this->answering)($prompt));

        expect($consent->confirm($this->output, '  Would you like to proceed?', 'refactor-plan', 'carry out the plan'))->toBeFalse();
        expect($this->output->fetch())->toContain('Nothing was written: there is nobody to answer. Run phpspec refactor in a terminal to carry out the plan.');
    });

    it('puts the question without asking when told nobody is there, and when told to accept', function (Prompt $prompt) {
        expect($prompt->ask(any()))->not()->toBeCalled();

        expect((new Consent('Run with --accept-offers to %s.', Generation::Declines, prompt: $prompt))->confirm($this->output, '  Create it?', 'create-class', 'create classes'))->toBeFalse();
        expect((new Consent('Run with --accept-offers to %s.', Generation::Accepts, prompt: $prompt))->confirm($this->output, '  Create it?', 'create-class', 'create classes'))->toBeTrue();
        expect($this->output->fetch())->toContain('Nothing was written: there is nobody to answer. Run with --accept-offers to create classes.');
    });

    it('asks through the pair chooser when there is one', function () {
        $screen = new BufferedOutput();
        $consent = new Consent('Run with --accept-offers to %s.', chooser: new Chooser(new PairOutput($screen), true, fn(): string => '3'));

        expect($consent->confirm($this->output, 'Apply this step?', 'refactor-step', 'apply the steps'))->toBeFalse();
        expect($screen->fetch())->toContain('Apply this step?');
        expect($this->output->fetch())->toBe('');
    });

    it('reads a free answer line, nothing when nobody is there', function (Prompt $prompt) {
        $consent = new Consent('Run with --accept-offers to %s.', prompt: ($this->answering)($prompt, 'web'));

        expect($consent->answer($this->output))->toBe('web');
        expect($consent->answer($this->output))->toBeNull();
    });
});
