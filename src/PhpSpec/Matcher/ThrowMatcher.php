<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\DelayedCall;
use PhpSpec\Factory\ReflectionFactory;

use PhpSpec\Exception\Example\MatcherException;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

class ThrowMatcher implements MatcherInterface
{
    private static $ignoredProperties = array('file', 'line', 'string', 'trace', 'previous');
    private $unwrapper;
    private $presenter;

    public function __construct(Unwrapper $unwrapper, PresenterInterface $presenter, ReflectionFactory $factory = null)
    {
        $this->unwrapper = $unwrapper;
        $this->presenter = $presenter;
        $this->factory   = $factory ?: new ReflectionFactory;
    }

    public function supports($name, $subject, array $arguments)
    {
        return 'throw' === $name;
    }

    public function positiveMatch($name, $subject, array $arguments)
    {
        return $this->getDelayedCall(array($this, 'verifyPositive'), $subject, $arguments);
    }

    public function negativeMatch($name, $subject, array $arguments)
    {
        return $this->getDelayedCall(array($this, 'verifyNegative'), $subject, $arguments);
    }

    public function verifyPositive($callable, array $arguments, $exception = null)
    {
        try {
            call_user_func_array($callable, $arguments);
        } catch (\Exception $e) {
            if (null === $exception) {
                return;
            }

            if (!$e instanceof $exception) {
                throw new FailureException(sprintf(
                    'Expected exception of class %s, but got %s.',
                    $this->presenter->presentValue($exception),
                    $this->presenter->presentValue($e)
                ));
            }

            if (is_object($exception)) {
                $exceptionRefl = $this->factory->create($exception);
                foreach ($exceptionRefl->getProperties() as $property) {
                    if (in_array($property->getName(), self::$ignoredProperties)) {
                        continue;
                    }

                    $property->setAccessible(true);
                    $expected = $property->getValue($exception);
                    $actual   = $property->getValue($e);

                    if (null !== $expected && $actual !== $expected) {
                        throw new NotEqualException(sprintf(
                            'Expected exception `%s` to be %s, but it is %s.',
                            $property->getName(),
                            $this->presenter->presentValue($expected),
                            $this->presenter->presentValue($actual)
                        ), $expected, $actual);
                    }
                }
            }

            return;
        }

        throw new FailureException('Expected to get exception, none got.');
    }

    public function verifyNegative($callable, array $arguments, $exception = null)
    {
        try {
            call_user_func_array($callable, $arguments);
        } catch (\Exception $e) {
            if (null === $exception) {
                throw new FailureException(sprintf(
                    'Expected to not throw any exceptions, but got %s.',
                    $this->presenter->presentValue($e)
                ));
            }

            if ($e instanceof $exception) {
                $invalidProperties = array();
                if (is_object($exception)) {
                    $exceptionRefl = $this->factory->create($exception);
                    foreach ($exceptionRefl->getProperties() as $property) {
                        if (in_array($property->getName(), self::$ignoredProperties)) {
                            continue;
                        }

                        $property->setAccessible(true);
                        $expected = $property->getValue($exception);
                        $actual   = $property->getValue($e);

                        if (null !== $expected && $actual === $expected) {
                            $invalidProperties[] = sprintf('  `%s`=%s',
                                $property->getName(),
                                $this->presenter->presentValue($expected)
                            );
                        }
                    }
                }

                $withProperties = '';
                if (count($invalidProperties) > 0) {
                    $withProperties = sprintf(' with'.PHP_EOL.'%s,'.PHP_EOL,
                        implode(",\n", $invalidProperties)
                    );
                }

                throw new FailureException(sprintf(
                    'Expected to not throw %s exception%s but got it.',
                    $this->presenter->presentValue($exception),
                    $withProperties
                ));
            }
        }
    }

    public function getPriority()
    {
        return 1;
    }

    private function getDelayedCall($check, $subject, array $arguments)
    {
        $exception = $this->getException($arguments);
        $unwrapper = $this->unwrapper;

        return new DelayedCall(
            function ($method, $arguments) use($check, $subject, $exception, $unwrapper) {
                $arguments = $unwrapper->unwrapAll($arguments);

                if (preg_match('/^during(.+)$/', $method, $matches)) {
                    $callable = lcfirst($matches[1]);
                } elseif (isset($arguments[0])) {
                    if (strpos($method, 'during') === false) {
                        throw new MatcherException('Incorrect usage of matcher Throw, either prefix the method with "during" and capitalize the first character of the method or use ->during(\'callable\', array(arguments)).' .PHP_EOL. 'E.g.'.PHP_EOL.'->during' . ucfirst($method) . '(arguments)'.PHP_EOL.'or'.PHP_EOL.'->during(\'' . $method . '\', array(arguments))');
                    }
                    $callable  = $arguments[0];
                    $arguments = isset($arguments[1]) ? $arguments[1] : array();
                } else {
                    throw new MatcherException('Provide callable to be checked for throwing.');
                }

                $callable = is_string($callable) ? array($subject, $callable) : $callable;
                
                list($class, $methodName) = $callable;
                if (!method_exists($class, $methodName) && !method_exists($class, '__call')) {
                    throw new MethodNotFoundException(
                        sprintf('Method %s::%s not found.', get_class($class), $methodName), 
                        $class, $methodName, $arguments
                    );
                }

                return call_user_func($check, $callable, $arguments, $exception);
            }
        );
    }

    private function getException(array $arguments)
    {
        if (0 == count($arguments)) {
            return null;
        }

        if (is_string($arguments[0])) {
            return $arguments[0];
        }

        if (is_object($arguments[0]) && $arguments[0] instanceof \Exception) {
            return $arguments[0];
        }

        throw new MatcherException(sprintf(
            "Wrong argument provided in throw matcher.\n".
            "Fully qualified classname or exception instance expected,\n".
            "Got %s.",
            $this->presenter->presentValue($arguments[0])
        ));
    }
}
