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

namespace PhpSpec\Process\Shutdown;

use PhpSpec\Formatter\CurrentExampleWriter;
use PhpSpec\Message\MessageInterface;

class Shutdown
{
    /**
     * @var Example
     */
    private $message;

    /**
     * @var CurrentExampleWriter
     */
    private $currentExampleWriter;

    public function __construct(MessageInterface $message, CurrentExampleWriter $currentExampleWriter)
    {
        $this->message = $message;
        $this->currentExampleWriter = $currentExampleWriter;
    }

    public function registerShutdown()
    {
        ini_set('display_errors', '0');
        error_reporting(E_NOTICE);
        register_shutdown_function(array($this, 'updateConsole'));
    }

    public function updateConsole()
    {
        $this->currentExampleWriter->displayFatal($this->message);
    }
}
