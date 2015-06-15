<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Formatter;

use PhpSpec\Console\IO;
use PhpSpec\Message\CurrentExample;

final class FatalErrorWriter implements FatalPresenterInterface
{
    private $output;

    public function __construct(IO $io)
    {
        $this->output = $io;
    }

    public function displayFatal(CurrentExample $currentExample, $error = null)
    {
        if (!empty($error) && $currentExample->getCurrentExample()) {
            $failedOpen = ($this->output->isDecorated()) ? '<failed>' : '';
            $failedClosed = ($this->output->isDecorated()) ? '</failed>' : '';
            $failedCross = ($this->output->isDecorated()) ? '✘' : '';

            $this->output->writeln("$failedOpen$failedCross Fatal error happened while executing the following $failedClosed");
            $this->output->writeln("$failedOpen    {$currentExample->getCurrentExample()} $failedClosed");
            $this->output->writeln("$failedOpen    {$error['message']} $failedClosed");
        }
    }
}
