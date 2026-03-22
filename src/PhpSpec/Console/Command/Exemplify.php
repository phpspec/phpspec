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
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
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
            ->setDescription('Add an example for a method to a spec file');
    }

    protected function execute(Input $input, Output $output): int
    {
        $class = $input->getArgument('class');
        $method = $input->getArgument('method');
        $spec = str_replace('\\', '/', $class);

        $this->generator->generate($spec);
        $this->generator->addExample($spec, $method);

        $output->writeln(sprintf(
            'Example for <info>%s::%s</info> added.',
            $class,
            $method,
        ));

        return 0;
    }
}
