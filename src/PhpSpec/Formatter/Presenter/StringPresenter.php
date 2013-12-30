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

namespace PhpSpec\Formatter\Presenter;

use Exception;
use PhpSpec\Exception\Exception as PhpSpecException;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Exception\Example\ErrorException;
use PhpSpec\Exception\Example\PendingException;

use Prophecy\Exception\Exception as ProphecyException;

/**
 * Class StringPresenter
 * @package PhpSpec\Formatter\Presenter
 */
class StringPresenter implements PresenterInterface
{
    /**
     * @var Differ\Differ
     */
    private $differ;

    /**
     * @param Differ\Differ $differ
     */
    public function __construct(Differ\Differ $differ)
    {
        $this->differ = $differ;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function presentValue($value)
    {
        if (is_callable($value)) {
            if (is_array($value)) {
                return $this->presentString(sprintf(
                    '[%s::%s()]', get_class($value[0]), $value[1]
                ));
            } elseif ($value instanceof \Closure) {
                return $this->presentString('[closure]');
            } elseif (is_object($value)) {
                return $this->presentString(sprintf('[obj:%s]', get_class($value)));
            } else {
                return $this->presentString(sprintf('[%s()]', $value));
            }
        }

        if (is_object($value) && $value instanceof Exception) {
            return $this->presentString(sprintf(
                '[exc:%s("%s")]', get_class($value), $value->getMessage()
            ));
        }

        switch ($type = strtolower(gettype($value))) {
            case 'null':
                return $this->presentString('null');
            case 'boolean':
                return $this->presentString(sprintf('%s', true === $value ? 'true' : 'false'));
            case 'object':
                return $this->presentString(sprintf('[obj:%s]', get_class($value)));
            case 'array':
                return $this->presentString(sprintf('[array:%d]', count($value)));
            case 'string':
                if (25 > strlen($value) && false === strpos($value, "\n")) {
                    return $this->presentString(sprintf('"%s"', $value));
                }

                $lines = explode("\n", $value);

                return $this->presentString(sprintf('"%s"...', substr($lines[0], 0, 25)));
            default:
                return $this->presentString(sprintf('[%s:%s]', $type, $value));
        }
    }

    /**
     * @param Exception $exception
     * @param bool      $verbose
     *
     * @return string
     */
    public function presentException(Exception $exception, $verbose = false)
    {
        $presentation = sprintf('Exception %s has been thrown.', $this->presentValue($exception));
        if ($exception instanceof PhpSpecException) {
            $presentation = wordwrap($exception->getMessage(), 120);
        }

        if ($exception instanceof ProphecyException) {
            $presentation = $exception->getMessage();
        }

        if (!$verbose || $exception instanceof PendingException) {
            return $presentation;
        }

        if ($exception instanceof NotEqualException) {
            if ($diff = $this->presentExceptionDifference($exception)) {
                $presentation .= "\n".$diff;
            }
        }

        if ($exception instanceof PhpSpecException && !$exception instanceof ErrorException) {
            list($file, $line) = $this->getExceptionExamplePosition($exception);

            $presentation .= "\n".$this->presentFileCode($file, $line);
        }

        if (trim($trace = $this->presentExceptionStackTrace($exception))) {
            $presentation .= "\n".$trace;
        }

        return $presentation;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function presentString($string)
    {
        return $string;
    }

    /**
     * @param string  $file
     * @param integer $lineno
     * @param integer $context
     *
     * @return string
     */
    protected function presentFileCode($file, $lineno, $context = 6)
    {
        $lines  = explode("\n", file_get_contents($file));
        $offset = max(0, $lineno - ceil($context / 2));
        $lines  = array_slice($lines, $offset, $context);

        $text = "\n";
        foreach ($lines as $line) {
            $offset++;

            if ($offset == $lineno) {
                $text .= $this->presentHighlight(sprintf('%4d', $offset).' '.$line);
            } else {
                $text .= $this->presentCodeLine(sprintf('%4d', $offset), $line);
            }

            $text .= "\n";
        }

        return $text;
    }

    /**
     * @param integer $number
     * @param integer $line
     *
     * @return string
     */
    protected function presentCodeLine($number, $line)
    {
        return $number.' '.$line;
    }

    /**
     * @param string $line
     *
     * @return string
     */
    protected function presentHighlight($line)
    {
        return $line;
    }

    /**
     * @param Exception $exception
     *
     * @return string
     */
    protected function presentExceptionDifference(Exception $exception)
    {
        return $this->differ->compare($exception->getExpected(), $exception->getActual());
    }

    /**
     * @param Exception $exception
     *
     * @return string
     */
    protected function presentExceptionStackTrace(Exception $exception)
    {
        $phpspecPath = dirname(dirname(__DIR__));
        $runnerPath  = $phpspecPath.DIRECTORY_SEPARATOR.'Runner';

        $offset = 0;
        $text   = "\n";

        $text .= $this->presentExceptionTraceHeader(sprintf("%2d %s:%d",
            $offset++,
            str_replace(getcwd().DIRECTORY_SEPARATOR, '', $exception->getFile()),
            $exception->getLine()
        ));
        $text .= $this->presentExceptionTraceFunction(
            'throw new '.get_class($exception), array($exception->getMessage())
        );

        foreach ($exception->getTrace() as $call) {
            // skip internal framework calls
            if (isset($call['file']) && false !== strpos($call['file'], $runnerPath)) {
                break;
            }
            if (isset($call['file']) && 0 === strpos($call['file'], $phpspecPath)) {
                continue;
            }
            if (isset($call['class']) && 0 === strpos($call['class'], "PhpSpec\\")) {
                continue;
            }

            if (isset($call['file'])) {
                $text .= $this->presentExceptionTraceHeader(sprintf("%2d %s:%d",
                    $offset++,
                    str_replace(getcwd().DIRECTORY_SEPARATOR, '', $call['file']),
                    $call['line']
                ));
            } else {
                $text .= $this->presentExceptionTraceHeader(sprintf("%2d [internal]", $offset++));
            }

            if (isset($call['class'])) {
                $text .= $this->presentExceptionTraceMethod(
                    $call['class'], $call['type'], $call['function'], isset($call['args']) ? $call['args'] : array()
                );
            } elseif (isset($call['function'])) {
                $text .= $this->presentExceptionTraceFunction(
                    $call['function'], isset($call['args']) ? $call['args'] : array()
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
    protected function presentExceptionTraceHeader($header)
    {
        return $header."\n";
    }

    /**
     * @param string $class
     * @param string $type
     * @param string $method
     * @param array  $args
     *
     * @return string
     */
    protected function presentExceptionTraceMethod($class, $type, $method, array $args)
    {
        $args = array_map(array($this, 'presentValue'), $args);

        return sprintf("   %s%s%s(%s)\n", $class, $type, $method, implode(', ', $args));
    }

    /**
     * @param string $function
     * @param array  $args
     *
     * @return string
     */
    protected function presentExceptionTraceFunction($function, array $args)
    {
        $args = array_map(array($this, 'presentValue'), $args);

        return sprintf("   %s(%s)\n", $function, implode(', ', $args));
    }

    /**
     * @param Exception $exception
     *
     * @return array
     */
    protected function getExceptionExamplePosition(Exception $exception)
    {
        $refl = $exception->getCause();
        foreach ($exception->getTrace() as $call) {
            if (!isset($call['file'])) {
                continue;
            }

            if (!empty($refl) && $refl->getFilename() === $call['file']) {
                return array($call['file'], $call['line']);
            }
        }

        return array($exception->getFile(), $exception->getLine());
    }
}
