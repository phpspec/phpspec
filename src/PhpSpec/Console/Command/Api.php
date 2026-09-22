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

use PhpSpec\Api\Surface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * Describes the spec-writing API from the code itself: the DSL, the matchers,
 * the mock and story functions, and the timing rules a reader cannot
 * introspect. As prose for a person, as one JSON object for a coding agent.
 *
 * @phpstan-import-type Entry from Surface
 * @phpstan-import-type Document from Surface
 */
final class Api extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('api')
            ->setDescription('Describe the spec-writing API: DSL, matchers, mocks, story steps and when expectations run')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format: pretty, or agent (one JSON object for coding agents)', 'pretty');
    }

    protected function execute(Input $input, Output $output): int
    {
        $version = $this->getApplication()?->getVersion();
        $document = (new Surface($version === 'UNKNOWN' ? null : $version))->toArray();

        if ($input->getOption('format') === 'agent') {
            $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            $output->write($json . "\n", false, Output::OUTPUT_RAW);

            return 0;
        }

        $this->prose($document, $output);

        return 0;
    }

    /**
     * @param Document $document
     */
    private function prose(array $document, Output $output): void
    {
        $output->writeln('<fg=white;options=bold>PhpSpec spec-writing API' . (isset($document['version']) ? ' ' . $document['version'] : '') . '</>');

        $this->heading($output, 'Timing');
        foreach ($document['timing'] as $rule) {
            $output->writeln('  - ' . $rule);
        }

        $this->entries($output, 'Spec DSL', $document['dsl']);
        $this->entries($output, 'Matchers', [$document['negation'], ...$document['matchers']]);
        $this->entries($output, 'Mocks', $document['mocks']['functions']);
        $this->entries($output, 'Mock expectations', $document['mocks']['expectations']);
        $this->entries($output, 'Argument matchers', $document['mocks']['argument_matchers']);
        $this->entries($output, 'Story BDD steps', $document['story']['steps']);
        $this->entries($output, 'Story BDD hooks', $document['story']['hooks']);
        $output->writeln('  ' . $document['story']['support']);
        $this->entries($output, 'Browser', $document['browser']['functions']);
        $output->writeln('  ' . $document['browser']['note']);

        $this->heading($output, 'Docs');
        foreach ($document['docs'] as $topic => $path) {
            $output->writeln('  ' . $topic . ': ' . $path);
        }
    }

    /**
     * @param list<Entry> $entries
     */
    private function entries(Output $output, string $title, array $entries): void
    {
        $this->heading($output, $title);

        foreach ($entries as $entry) {
            $output->writeln('  <fg=yellow>' . $entry['signature'] . '</>' . (isset($entry['runs_subject']) ? '  <fg=gray>runs the subject where written</>' : ''));
            $output->writeln('      ' . $entry['summary']);
        }
    }

    private function heading(Output $output, string $title): void
    {
        $output->writeln('');
        $output->writeln('<fg=white;options=bold>' . $title . '</>');
    }
}
