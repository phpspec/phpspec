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
use PhpSpec\Message\CurrentExample;

final class UpdateConsoleAction implements ShutdownActionInterface
{
    /**
     * @var CurrentExample
     */
    private $currentExample;

    /**
     * @var CurrentExampleWriter
     */
    private $currentExampleWriter;

    public function __construct(CurrentExample $currentExample, CurrentExampleWriter $currentExampleWriter)
    {
        $this->currentExample = $currentExample;
        $this->currentExampleWriter = $currentExampleWriter;
    }

    public function runAction()
    {
        $this->output();
    }

    public function output()
    {
        $this->currentExampleWriter->displayFatal($this->currentExample);
    }
}
