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

class Shutdown
{
    protected $actions;

    public function __construct()
    {
        $this->actions = array();
    }

    public function registerShutdown()
    {
        ini_set('display_errors', '0');
        error_reporting(E_ERROR);
        register_shutdown_function(array($this, 'runShutdown'));
    }

    public function registerAction(ShutdownActionInterface $action)
    {
        $this->actions[] = $action;
    }

    public function runShutdown()
    {
        foreach ($this->actions as $shutdownActions) {
            $shutdownActions->runAction();
        }
    }
}
