<?php

namespace PhpSpec\Formatter\Presenter;

use Exception;
use PhpSpec\Exception\Exception as PhpSpecException;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Exception\Example\ErrorException;
use PhpSpec\Exception\Example\PendingException;

use Prophecy\Exception\Exception as ProphecyException;

class StringPresenter implements PresenterInterface
{
    private $differ;

    public function __construct(Differ\Differ $differ)
    {
        $this->differ = $differ;
    }

    public function presentValue($value)
    {
        if (is_callable($value)) {
            if (is_array($value)) {
                return $this->presentString(sprintf(
                    '[%s::%s()]', get_class($value[0]), $value[1]
                ));
            } elseif ($value instanceof \Closure) {
                return $this->presentString('[closure]');
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
                return $presentation."\n".$diff;
            }
        }

        if ($exception instanceof PhpSpecException && !$exception instanceof ErrorException) {
            list($file, $line) = $this->getExceptionExamplePosition($exception);

            return $presentation."\n".$this->presentFileCode($file, $line);
        }

        if (trim($trace = $this->presentExceptionStackTrace($exception))) {
            return $presentation."\n".$trace;
        }

        return $presentation;
    }

    public function presentString($string)
    {
        return $string;
    }

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

    protected function presentCodeLine($number, $line)
    {
        return $number.' '.$line;
    }

    protected function presentHighlight($line)
    {
        return $line;
    }

    protected function presentExceptionDifference(Exception $exception)
    {
        return $this->differ->compare($exception->getExpected(), $exception->getActual());
    }

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
                    $call['class'], $call['type'], $call['function'], $call['args']
                );
            } elseif (isset($call['function'])) {
                $args = array_map(array($this, 'presentValue'), $call['args']);

                $text .= $this->presentExceptionTraceFunction(
                    $call['function'], $call['args']
                );
            }
        }

        return $text;
    }

    protected function presentExceptionTraceHeader($header)
    {
        return $header."\n";
    }

    protected function presentExceptionTraceMethod($class, $type, $method, array $args)
    {
        $args = array_map(array($this, 'presentValue'), $args);

        return sprintf("   %s%s%s(%s)\n", $class, $type, $method, implode(', ', $args));
    }

    protected function presentExceptionTraceFunction($function, array $args)
    {
        $args = array_map(array($this, 'presentValue'), $args);

        return sprintf("   %s(%s)\n", $function, implode(', ', $args));
    }

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
