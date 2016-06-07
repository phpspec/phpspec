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

use PhpSpec\Process\Context\ExecutionContextInterface;
use Symfony\Component\Process\PhpExecutableFinder;

final class ProcOpenReRunner extends PhpExecutableReRunner
{
    /**
     * @var ExecutionContextInterface
     */
    private $executionContext;

    /**
     * @param PhpExecutableFinder $phpExecutableFinder
     * @param ExecutionContextInterface $executionContext
     * @return static
     */
    public static function withExecutionContext(PhpExecutableFinder $phpExecutableFinder, ExecutionContextInterface $executionContext)
    {
        $reRunner = new static($phpExecutableFinder);
        $reRunner->executionContext = $executionContext;

        return $reRunner;
    }

    /**
     * @return boolean
     */
    public function isSupported()
    {
        return (php_sapi_name() == 'cli')
            && $this->getExecutablePath()
            && function_exists('passthru')
            && (stripos(PHP_OS, "win") !== 0);
    }

    public function reRunSuite()
    {
        $args = $_SERVER['argv'];
        $command = $this->buildArgString() . escapeshellcmd($this->getExecutablePath()).' '.join(' ', array_map('escapeshellarg', $args)) . ' 2>&1';

        $desc = array(
            0 => array('file', 'php://stdin', 'r'),
            1 => array('file', 'php://stdout', 'w'),
            2 => array('file', 'php://stderr', 'w'),
        );
        $proc = proc_open( $command, $desc, $pipes );

        do {
            sleep(1);
            $status = proc_get_status($proc);
        } while ($status['running']);

        exit($status['exitcode']);
    }

    private function buildArgString()
    {
        $argstring = '';

        foreach ($this->executionContext->asEnv() as $key => $value) {
            $argstring .= $key . '=' . escapeshellarg($value) . ' ';
        }

        return $argstring;
    }
}
