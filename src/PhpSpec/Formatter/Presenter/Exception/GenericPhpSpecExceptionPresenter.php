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

namespace PhpSpec\Formatter\Presenter\Exception;

use PhpSpec\Exception\Exception;

final class GenericPhpSpecExceptionPresenter implements PhpSpecExceptionPresenter
{
    /**
     * @var ExceptionElementPresenter
     */
    private $exceptionElementPresenter;

    /**
     * @param ExceptionElementPresenter $exceptionElementPresenter
     */
    public function __construct(ExceptionElementPresenter $exceptionElementPresenter)
    {
        $this->exceptionElementPresenter = $exceptionElementPresenter;
    }

    /**
     * @param Exception $exception
     * @return string
     */
    public function presentException(Exception $exception)
    {
        list($file, $line) = $this->getExceptionExamplePosition($exception);

        return $this->presentFileCode($file, $line);
    }

    /**
     * @param Exception $exception
     * @return array
     */
    private function getExceptionExamplePosition(Exception $exception)
    {
        $cause = $exception->getCause();

        foreach ($exception->getTrace() as $call) {
            if (!isset($call['file'])) {
                continue;
            }

            if (!empty($cause) && $cause->getFilename() === $call['file']) {
                return array($call['file'], $call['line']);
            }
        }

        return array($exception->getFile(), $exception->getLine());
    }

    /**
     * @param string  $file
     * @param integer $lineno
     * @param integer $context
     *
     * @return string
     */
    private function presentFileCode($file, $lineno, $context = 6)
    {
        $lines  = explode(PHP_EOL, file_get_contents($file));
        $offset = max(0, $lineno - ceil($context / 2));
        $lines  = array_slice($lines, $offset, $context);

        $text = PHP_EOL;
        foreach ($lines as $line) {
            $offset++;

            if ($offset == $lineno) {
                $text .= $this->exceptionElementPresenter->presentHighlight(sprintf('%4d', $offset).' '.$line);
            } else {
                $text .= $this->exceptionElementPresenter->presentCodeLine(sprintf('%4d', $offset), $line);
            }

            $text .= PHP_EOL;
        }

        return $text;
    }
}
