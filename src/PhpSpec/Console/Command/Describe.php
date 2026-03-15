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

use PhpSpec\CodeGeneration\SpecGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;

/**
 * CLI command that generates spec files for a given class. Optionally adds an example
 * for a specific method and can trigger a spec run after generation.
 */
final class Describe extends Command
{
    /**
     * @param SpecGenerator $generator the spec file generator
     * @param string|null $name the command name (defaults to "describe")
     */
    public function __construct(private readonly SpecGenerator $generator, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * Configures the command name, arguments, and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('describe')
            ->setDefinition([
                new Argument(
                    'class',
                    Argument::OPTIONAL,
                    'Class you want to describe',
                ),
            ])
            ->addOption('exemplify', 'e', Option::VALUE_REQUIRED, 'Add an example for a method')
            ->addOption('run', 'r', Option::VALUE_NONE, 'Run specs after generating')
            ->setDescription('Generate spec for a class');
    }

    /**
     * Generates a spec file, optionally adds a method example, and optionally runs specs.
     *
     * @param Input $input the console input
     * @param Output $output the console output
     * @return int the exit code (0 for success)
     * @throws ExceptionInterface
     */
    protected function execute(Input $input, Output $output): int
    {
        $class = $input->getArgument('class');
        $spec = str_replace('\\', '/', $class);
        $this->generator->generate($spec);
        $specPath = $this->generator->getSpecPath();
        $output->writeln(
            'Specification for ' . $class .
            ' created in ' . getcwd() . DIRECTORY_SEPARATOR .
            $specPath . DIRECTORY_SEPARATOR .
            str_replace('\\', DIRECTORY_SEPARATOR, $class) .
            $this->generator->getSpecSuffix(),
        );

        $method = $input->getOption('exemplify');
        if ($method) {
            $this->generator->addExample($spec, $method);
            $output->writeln("Example for method <info>$method</info> added.");
        }

        if ($input->getOption('run')) {
            return $this->getApplication()->find('run')->run(new ArrayInput([]), $output);
        }

        return 0;
    }
}
