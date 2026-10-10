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

namespace PhpSpec\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * The help command, describing a command's text help through HelpText so
 * the options only phpspec passes stay out of it.
 */
final class Help extends HelpCommand
{
    private ?Command $described = null;

    public function setCommand(Command $command): void
    {
        $this->described = $command;

        parent::setCommand($command);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = $this->described ?? $this->getApplication()?->find($input->getArgument('command_name'));

        if ($command === null) {
            return parent::execute($input, $output);
        }

        $helper = new DescriptorHelper();
        $helper->register('txt', new HelpText());
        $helper->describe($output, $command, [
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
        ]);
        $this->described = null;

        return 0;
    }
}
