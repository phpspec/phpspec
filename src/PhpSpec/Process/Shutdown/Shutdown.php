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

final class Shutdown
{
    protected $actions;

    const HHVM_FATAL_ERROR = 16777217;

    public function __construct()
    {
        $this->actions = array();
    }

    public function registerShutdown()
    {
        error_reporting(E_ALL | E_STRICT);
        register_shutdown_function(array($this, 'runShutdown'));
    }

    public function registerAction(ShutdownActionInterface $action)
    {
        $this->actions[] = $action;
    }

    public function runShutdown()
    {
        $error = $this->getFatalError();

        foreach ($this->actions as $fatalErrorActions) {
            $fatalErrorActions->runAction($error);
        }
    }

    private function getFatalError()
    {
        $error = error_get_last();
        $fatal = false;

        if (!empty($error)) {
            $fatal = defined('HHVM_VERSION') ? (self::HHVM_FATAL_ERROR === $error['type']) : (E_ERROR === $error['type']);
        }

        return $fatal ? $error : null;
    }
}
