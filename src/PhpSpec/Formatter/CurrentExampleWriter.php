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

class CurrentExampleWriter
{
    private $output;

    public function __construct(IO $io)
    {
        $this->output = $io;
    }

    public function displayFatal(CurrentExample $message)
    {
        $error = error_get_last();

        if (!empty($error) && $message->getCurrentExample()) {
            $this->output->writeln("Fatal error happened while executing the following example");
            $this->output->writeln($message->getCurrentExample());
            $this->output->writeln($error['message']);
        }
    }
}
