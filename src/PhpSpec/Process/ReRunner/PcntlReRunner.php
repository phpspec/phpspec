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

use PhpSpec\Process\RerunContext;
use Symfony\Component\Process\PhpExecutableFinder;

class PcntlReRunner extends PhpExecutableReRunner
{
    /**
     * @var RerunContext
     */
    private $context;

    public function __construct(PhpExecutableFinder $executableFinder, RerunContext $context)
    {
        parent::__construct($executableFinder);
        $this->context = $context;
    }

    /**
     * @return bool
     */
    public function isSupported()
    {
        return (php_sapi_name() == 'cli')
            && $this->getExecutablePath()
            && function_exists('pcntl_exec');
    }

    /**
     * Kills the current process and starts a new one
     */
    public function reRunSuite()
    {
        $args = $_SERVER['argv'];
        $envs = array_merge($_ENV, $_SERVER, array(RerunContext::ENV_NAME=>$this->context->asString()));
        pcntl_exec($this->getExecutablePath(), $args, $envs);
    }
}
