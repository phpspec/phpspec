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

use PhpSpec\CodeGeneration\PhpName;
use PhpSpec\CodeGeneration\SpecGenerator;
use PhpSpec\Report\Formatter\Agent\Schema;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * @internal
 * CLI command that adds an it() example for a method to an existing spec file,
 * generating the spec file first if it doesn't exist.
 */
final class Exemplify extends Command
{
    /**
     * @param SpecGenerator $generator the spec file generator
     * @param string|null $name the command name (defaults to "exemplify")
     */
    public function __construct(private readonly SpecGenerator $generator, ?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setName('exemplify')
            ->setDefinition([
                new Argument('class', Argument::REQUIRED, 'Class to add the example to'),
                new Argument('method', Argument::REQUIRED, 'Method to exemplify'),
            ])
            ->addOption('agent', null, Option::VALUE_NONE, 'Deprecated alias of --format=agent')
            ->addOption('format', 'f', Option::VALUE_REQUIRED, 'Output format: pretty, or agent (machine-readable JSON receipt for coding agents)', 'pretty')
            ->setDescription('Add an example for a method to a spec file');
    }

    protected function execute(Input $input, Output $output): int
    {
        $class = (string) $input->getArgument('class');
        $method = (string) $input->getArgument('method');
        $forAgent = $input->getOption('agent') || $input->getOption('format') === 'agent';

        // Both names are judged before either is written: an invalid method must
        // not leave a spec file behind as a souvenir of a refused command.
        $problem = PhpName::classProblem($class) ?? PhpName::methodProblem($method);

        if ($problem !== null) {
            if ($forAgent) {
                $json = json_encode(
                    ['v' => Schema::V, 'action' => 'exemplify', 'error' => $problem],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ) ?: '{}';
                $output->write($json . "\n", false, Output::OUTPUT_RAW);

                return 1;
            }

            $output->writeln(sprintf('<fg=red>%s</>', $problem));

            return 1;
        }

        $spec = str_replace('\\', '/', $class);

        $this->generator->generate($spec);
        $added = $this->generator->addExample($spec, $method);

        if ($forAgent) {
            $receipt = [
                'v' => Schema::V,
                'action' => 'exemplify',
                'class' => str_replace('/', '\\', $spec),
                'method' => $method,
                'spec' => $this->generator->getSpecPath() . '/' . $spec . $this->generator->getSpecSuffix(),
                'added' => $added,
            ];

            $json = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            $output->write($json . "\n", false, Output::OUTPUT_RAW);

            return 0;
        }

        $output->writeln(sprintf(
            'Example for <info>%s::%s</info> added.',
            $class,
            $method,
        ));

        return 0;
    }
}
