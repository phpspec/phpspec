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

namespace PhpSpec\Process\ReRunner;

use PhpSpec\Process\Context\ExecutionContext;
use Symfony\Component\Process\PhpExecutableFinder;

final class PcntlReRunner extends PhpExecutableReRunner
{
    /**
     * @var ExecutionContext
     */
    private $executionContext;

    /**
     * @param PhpExecutableFinder $phpExecutableFinder
     * @param ExecutionContext $executionContext
     * @return static
     */
    public static function withExecutionContext(PhpExecutableFinder $phpExecutableFinder, ExecutionContext $executionContext)
    {
        $reRunner = new static($phpExecutableFinder);
        $reRunner->executionContext = $executionContext;

        return $reRunner;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        return (php_sapi_name() == 'cli')
            && $this->getExecutablePath()
            && function_exists('pcntl_exec')
            && !defined('HHVM_VERSION');
    }

    /**
     * Kills the current process and starts a new one
     */
    public function reRunSuite()
    {
        $args = $_SERVER['argv'];
        $env = $this->executionContext ? $this->executionContext->asEnv() : array();

        pcntl_exec($this->getExecutablePath(), $args, array_merge($env, $_SERVER));
    }
}
