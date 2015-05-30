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
use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Listener\StatisticsCollector;
use PhpSpec\Message\Example;
use PhpSpec\Message\MessageInterface;

class FatalFormatter extends ConsoleFormatter
{
    private $output;

    public function __construct(PresenterInterface $presenter, IO $io, StatisticsCollector $stats)
    {
        parent::__construct($presenter, $io, $stats);
        $this->output = $io;
    }

    public function displayFatal(MessageInterface $message)
    {
        $error = error_get_last();

        if (!empty($error) && $message->getMessage()) {
            $this->output->writeln("Error Happened while executing the following example");
            $this->output->writeln($message->getMessage());
            $this->output->writeln((error_get_last()['message']));
        }
    }
}
