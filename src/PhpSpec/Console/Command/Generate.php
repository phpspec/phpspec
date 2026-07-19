<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Command;

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Generate\GenerateAgent;
use PhpSpec\Console\Command\Refactor\Diff;
use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 * CLI command that turns a natural-language instruction into one file edit —
 * a spec example or a piece of implementation code — authored by the AI, shown
 * as a diff, and written after a confirmation. Requires an AI provider.
 */
final class Generate extends Command
{
    private Filesystem $filesystem;

    /** @var (callable(array{provider: string, model?: string, api_key: string}, string): (string|null))|null */
    private $chatFn;

    /**
     * @param Configuration $config
     * @param Filesystem|null $filesystem
     * @param (callable(array{provider: string, model?: string, api_key: string}, string): (string|null))|null $chatFn injectable AI seam for specs
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        ?callable $chatFn = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        $this->chatFn = $chatFn;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate a spec example or code from a natural-language instruction (requires AI)')
            ->addArgument('instruction', Argument::IS_ARRAY, 'What to build, in plain English');
    }

    protected function execute(Input $input, Output $output): int
    {
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $output->writeln('<fg=red>AI configuration required. Add an "ai" section to your phpspec config.</>');

            return 1;
        }

        $argument = $input->getArgument('instruction');
        $instruction = trim(implode(' ', is_array($argument) ? $argument : []));
        if ($instruction === '') {
            $output->writeln('<fg=red>Usage: generate <what to build in plain English></>');

            return 1;
        }

        $output->writeln('');
        $output->writeln('  <fg=gray>Generating...</>');
        $output->writeln('');

        $agent = new GenerateAgent($this->config, $this->filesystem, $this->chatFn);
        $proposal = $agent->propose($aiConfig, $instruction);

        if ($proposal === null) {
            $output->writeln('  <fg=yellow>Could not generate anything for that instruction. Try rephrasing.</>');

            return 1;
        }

        $this->showDiff($output, $proposal);

        if ($input->isInteractive() && !$this->confirm($input, $output)) {
            $output->writeln('  <fg=gray>Left unchanged.</>');

            return 0;
        }

        $agent->write($proposal);
        $output->writeln(sprintf('  <fg=green>%s %s</>', $proposal['isNew'] ? 'Created' : 'Updated', $proposal['path']));

        return 0;
    }

    /**
     * Renders the proposed change as a labelled unified diff.
     *
     * @param array{path: string, old: string, new: string, isNew: bool} $proposal
     */
    private function showDiff(Output $output, array $proposal): void
    {
        $label = $proposal['isNew'] ? '[NEW FILE]' : '[MODIFIED]';
        $output->writeln("  <fg=yellow>$label</> <fg=white>{$proposal['path']}</>");
        $output->writeln('');

        $old = $proposal['old'] === '' ? [] : explode("\n", $proposal['old']);
        $new = explode("\n", $proposal['new']);
        $output->writeln(Diff::format(Diff::compute($old, $new)));
        $output->writeln('');
    }

    private function confirm(Input $input, Output $output): bool
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return (bool) $helper->ask($input, $output, new ConfirmationQuestion('  <fg=yellow>Apply this change?</> [Y/n] ', true));
    }
}
