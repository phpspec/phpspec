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
        allow($prompt->ask())->toReturnUsing(function () use (&$answers): ?string {
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

    it('says nothing was written, and how to have it anyway, when nobody answers', function (Prompt $prompt) {
        $consent = new Consent('Run phpspec refactor in a terminal to %s.', prompt: ($this->answering)($prompt));

        expect($consent->confirm($this->output, '  Would you like to proceed?', 'refactor-plan', 'carry out the plan'))->toBeFalse();
        expect($this->output->fetch())->toContain('Nothing was written: there is nobody to answer. Run phpspec refactor in a terminal to carry out the plan.');
    });

    it('puts the question without asking when told nobody is there, and when told to accept', function (Prompt $prompt) {
        expect($prompt->ask())->not()->toBeCalled();

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
