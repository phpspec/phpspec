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

use PhpSpec\Exception\Example\ErrorException;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Exception\Example\PendingException;
use PhpSpec\Exception\Exception as PhpSpecException;
use PhpSpec\Formatter\Presenter\Differ\Differ;
use Prophecy\Exception\Call\UnexpectedCallException;
use Prophecy\Exception\Exception as ProphecyException;

final class SimpleExceptionPresenter implements ExceptionPresenter
{
    /**
     * @var Differ
     */
    private $differ;

    /**
     * @var string
     */
    private $phpspecPath;

    /**
     * @var string
     */
    private $runnerPath;

    /**
     * @var ExceptionElementPresenter
     */
    private $exceptionElementPresenter;

    /**
     * @var CallArgumentsPresenter
     */
    private $callArgumentsPresenter;

    /**
     * @param Differ $differ
     * @param ExceptionElementPresenter $exceptionElementPresenter
     * @param CallArgumentsPresenter $callArgumentsPresenter
     */
    public function __construct(
        Differ $differ,
        ExceptionElementPresenter $exceptionElementPresenter,
        CallArgumentsPresenter $callArgumentsPresenter
    ) {
        $this->differ = $differ;
        $this->exceptionElementPresenter = $exceptionElementPresenter;
        $this->callArgumentsPresenter = $callArgumentsPresenter;

        $this->phpspecPath = dirname(dirname(__DIR__));
        $this->runnerPath  = $this->phpspecPath.DIRECTORY_SEPARATOR.'Runner';
    }

    /**
     * @param \Exception $exception
     * @param bool $verbose
     * @return string
     */
    public function presentException(\Exception $exception, $verbose = false)
    {
        $presentation = $this->getInitialPresentation($exception);

        if (!$verbose || $exception instanceof PendingException) {
            return $presentation;
        }

        return $this->getVerbosePresentation($exception, $presentation);
    }

    /**
     * @param \Exception $exception
     * @return string
     */
    private function getInitialPresentation(\Exception $exception)
    {
        if ($exception instanceof PhpSpecException) {
            $presentation = wordwrap($exception->getMessage(), 120);
            return $presentation;
        }

        if ($exception instanceof ProphecyException) {
            $presentation = $exception->getMessage();
            return $presentation;
        }

        return $this->exceptionElementPresenter->presentExceptionThrownMessage($exception);
    }

    /**
     * @param \Exception $exception
     * @param string $presentation
     * @return string
     */
    private function getVerbosePresentation(\Exception $exception, $presentation)
    {
        if ($exception instanceof NotEqualException) {
            if ($diff = $this->presentExceptionDifference($exception)) {
                $presentation .= PHP_EOL . $diff;
            }
        }

        if ($exception instanceof PhpSpecException && !$exception instanceof ErrorException) {
            list($file, $line) = $this->getExceptionExamplePosition($exception);

            $presentation .= PHP_EOL . $this->presentFileCode($file, $line);
        }

        if ($exception instanceof UnexpectedCallException) {
            $presentation .= $this->callArgumentsPresenter->presentDifference($exception);
        }

        if (trim($trace = $this->presentExceptionStackTrace($exception))) {
            $presentation .= "\n".$trace;
        }

        return $presentation;
    }

    /**
     * @param NotEqualException $exception
     *
     * @return string
     */
    private function presentExceptionDifference(NotEqualException $exception)
    {
        return $this->differ->compare($exception->getExpected(), $exception->getActual());
    }

    /**
     * @param PhpSpecException $exception
     *
     * @return array
     */
    private function getExceptionExamplePosition(PhpSpecException $exception)
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

    /**
     * @param \Exception $exception
     *
     * @return string
     */
    private function presentExceptionStackTrace(\Exception $exception)
    {
        $offset = 0;
        $text = PHP_EOL;

        $text .= $this->presentExceptionTraceLocation($offset++, $exception->getFile(), $exception->getLine());
        $text .= $this->presentExceptionTraceFunction(
            'throw new '.get_class($exception),
            array($exception->getMessage())
        );

        foreach ($exception->getTrace() as $call) {
            // skip internal framework calls
            if ($this->shouldStopTracePresentation($call)) {
                break;
            }
            if ($this->shouldSkipTracePresentation($call)) {
                continue;
            }

            if (isset($call['file'])) {
                $text .= $this->presentExceptionTraceLocation($offset++, $call['file'], $call['line']);
            } else {
                $text .= $this->presentExceptionTraceHeader(sprintf("%2d [internal]", $offset++));
            }

            if (isset($call['class'])) {
                $text .= $this->presentExceptionTraceMethod(
                    $call['class'],
                    $call['type'],
                    $call['function'],
                    isset($call['args']) ? $call['args'] : array()
                );
            } elseif (isset($call['function'])) {
                $text .= $this->presentExceptionTraceFunction(
                    $call['function'],
                    isset($call['args']) ? $call['args'] : array()
                );
            }
        }

        return $text;
    }

    /**
     * @param string $header
     *
     * @return string
     */
    private function presentExceptionTraceHeader($header)
    {
        return $this->exceptionElementPresenter->presentExceptionTraceHeader($header) . PHP_EOL;
    }

    /**
     * @param string $class
     * @param string $type
     * @param string $method
     * @param array  $args
     *
     * @return string
     */
    private function presentExceptionTraceMethod($class, $type, $method, array $args)
    {
        return $this->exceptionElementPresenter->presentExceptionTraceMethod($class, $type, $method, $args) . PHP_EOL;
    }

    /**
     * @param string $function
     * @param array  $args
     *
     * @return string
     */
    private function presentExceptionTraceFunction($function, array $args)
    {
        return $this->exceptionElementPresenter->presentExceptionTraceFunction($function, $args) . PHP_EOL;
    }

    /**
     * @param int    $offset
     * @param string $file
     * @param int    $line
     *
     * @return string
     */
    private function presentExceptionTraceLocation($offset, $file, $line)
    {
        return $this->presentExceptionTraceHeader(sprintf(
            "%2d %s:%d",
            $offset,
            str_replace(getcwd().DIRECTORY_SEPARATOR, '', $file),
            $line
        ));
    }

    /**
     * @param array $call
     * @return bool
     */
    private function shouldStopTracePresentation(array $call)
    {
        return isset($call['file']) && false !== strpos($call['file'], $this->runnerPath);
    }

    /**
     * @param array $call
     * @return bool
     */
    private function shouldSkipTracePresentation(array $call)
    {
        if (isset($call['file']) && 0 === strpos($call['file'], $this->phpspecPath)) {
            return true;
        }

        return isset($call['class']) && 0 === strpos($call['class'], "PhpSpec\\");
    }

}
