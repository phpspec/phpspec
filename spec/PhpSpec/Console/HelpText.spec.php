<?php

use PhpSpec\Console\HelpText;
use PhpSpec\Console\InternalOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

final class HelpTextSpecCommand extends Command implements InternalOptions
{
    protected function configure(): void
    {
        $this->setName('spec-run')
            ->addOption('coverage-min', null, InputOption::VALUE_REQUIRED, 'Fail below this percentage')
            ->addOption('coverage-partial', null, InputOption::VALUE_REQUIRED, 'Dump raw coverage state for a worker');
    }

    public function internalOptions(): array
    {
        return ['coverage-partial'];
    }
}

describe(HelpText::class, function () {

    it('describes a command without the options it keeps for itself', function () {
        $output = new BufferedOutput();
        (new HelpText())->describe($output, new HelpTextSpecCommand());

        $text = $output->fetch();
        expect($text)->toContain('--coverage-min');
        expect($text)->not()->toContain('coverage-partial');
    });

    it('describes every option of a command that keeps none for itself', function () {
        $plain = new Command('plain');
        $plain->addOption('coverage-partial', null, InputOption::VALUE_REQUIRED, 'Shown here');
        $output = new BufferedOutput();
        (new HelpText())->describe($output, $plain);

        expect($output->fetch())->toContain('--coverage-partial');
    });
});
