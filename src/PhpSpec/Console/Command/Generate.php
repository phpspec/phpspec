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

use PhpSpec\Ai\Agent\Agent;
use PhpSpec\Ai\Agent\Outcome;
use PhpSpec\Ai\Agent\OutcomePresenter;
use PhpSpec\Ai\Agent\Proposal;
use PhpSpec\Ai\Agent\Writer;
use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Refactor\Diff;
use PhpSpec\Filesystem;
use PhpSpec\Offers\Offer;
use PhpSpec\Offers\OfferBook;
use PhpSpec\RealFilesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 * CLI command that turns a natural-language instruction into one artifact
 * through the agent pipeline: deterministic when the step fully determines it
 * (an explicit path, a feature skeleton, the steps of a known feature), the AI
 * otherwise. Every proposal is shown as a diff and written after confirmation.
 */
final class Generate extends Command
{
    private Filesystem $filesystem;

    /**
     * @param Configuration $config
     * @param Filesystem|null $filesystem
     * @param ProviderInterface|null $provider injectable AI seam for specs
     */
    public function __construct(
        private readonly Configuration $config,
        ?Filesystem $filesystem = null,
        private readonly ?ProviderInterface $provider = null,
    ) {
        $this->filesystem = $filesystem ?? new RealFilesystem();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate a feature, steps, spec, or code from a natural-language instruction (requires AI)')
            ->addArgument('instruction', Argument::IS_ARRAY, 'What to build, in plain English')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format: pretty, or agent (machine-readable JSON receipt for coding agents)', 'pretty');
    }

    protected function execute(Input $input, Output $output): int
    {
        $aiConfig = $this->config->getAiConfig();
        if ($aiConfig === null) {
            $output->writeln('<fg=red>' . $this->config->aiConfigProblem() . '</>');

            return 1;
        }

        $argument = $input->getArgument('instruction');
        $instruction = trim(implode(' ', is_array($argument) ? $argument : []));
        if ($instruction === '') {
            $output->writeln('<fg=red>Usage: generate <what to build in plain English></>');

            return 1;
        }

        $forAgent = $input->getOption('format') === 'agent';
        if (!$forAgent) {
            $output->writeln('');
            $output->writeln('  <fg=gray>Generating...</>');
            $output->writeln('');
        }

        $agent = new Agent($this->config, $this->filesystem, $this->provider);
        $outcome = $agent->chat('generate', $instruction);

        if ($forAgent) {
            return $this->generateForAgent($output, $outcome);
        }

        if ($outcome->proposals === []) {
            $reason = $outcome->prose !== '' ? $outcome->prose : 'Could not generate anything for that instruction. Try rephrasing.';
            $output->writeln("  <fg=yellow>$reason</>");

            return 1;
        }

        $writer = new Writer($this->filesystem);
        $offered = [];

        foreach ($outcome->proposals as $proposal) {
            $this->showDiff($output, $proposal);

            if ($input->isInteractive()) {
                if (!$this->confirm($input, $output)) {
                    $output->writeln('  <fg=gray>Left unchanged.</>');

                    continue;
                }

                $writer->apply($proposal);
                $output->writeln(sprintf('  <fg=green>%s %s</>', $proposal->isNew ? 'Created' : 'Updated', $proposal->path));

                continue;
            }

            // Nobody to ask is not the same as a yes. The change waits on the
            // table under an id, for whoever reads this to accept.
            $offered[] = $this->offer($proposal);
        }

        if ($offered !== []) {
            $this->book()->record(...$offered);
            $output->writeln('');
            foreach ($offered as $offer) {
                $output->writeln(sprintf('  <fg=yellow>Offered %s</> <fg=gray>%s</>', $offer->id, $offer->path));
            }
            $output->writeln(sprintf('  <fg=gray>Apply with:</> phpspec accept %s', implode(' ', array_map(static fn(Offer $offer): string => $offer->id, $offered))));
        }

        return 0;
    }

    /**
     * The agent path: nothing is applied here either. Each proposal goes on the
     * table with an id, and the receipt names it, so the reader accepts what it
     * has actually read rather than whatever a second call would produce.
     */
    private function generateForAgent(Output $output, Outcome $outcome): int
    {
        $offers = array_map(fn(Proposal $proposal): Offer => $this->offer($proposal), $outcome->proposals);

        if ($offers !== []) {
            $this->book()->record(...$offers);
        }

        $presenter = new OutcomePresenter();
        $document = $presenter->document('generate', $outcome, array_fill(0, count($offers), false));

        foreach ($offers as $index => $offer) {
            $document['proposals'][$index]['id'] = $offer->id;
        }

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $output->write($json . "\n", false, Output::OUTPUT_RAW);

        return $outcome->proposals === [] ? 1 : 0;
    }

    /**
     * The proposal as an offer that can be accepted later, remembering the file
     * as it stands now so a stale acceptance is refused.
     */
    private function offer(Proposal $proposal): Offer
    {
        return Offer::write($proposal->path, $proposal->new, $proposal->isNew, $proposal->old);
    }

    private function book(): OfferBook
    {
        return new OfferBook($this->filesystem);
    }

    /**
     * Renders a proposed change as a labelled unified diff.
     */
    private function showDiff(Output $output, Proposal $proposal): void
    {
        $label = $proposal->isNew ? '[NEW FILE]' : '[MODIFIED]';
        $output->writeln("  <fg=yellow>$label</> <fg=white>{$proposal->path}</>");
        $output->writeln('');

        $old = $proposal->old === '' ? [] : explode("\n", $proposal->old);
        $new = explode("\n", $proposal->new);
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
