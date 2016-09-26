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

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\DelayedCall;
use PhpSpec\Factory\ReflectionFactory;
use PhpSpec\Exception\Example\MatcherException;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

final class ThrowMatcher implements Matcher
{
    /**
     * @var array
     */
    private static $ignoredProperties = array('file', 'line', 'string', 'trace', 'previous');

    /**
     * @var Unwrapper
     */
    private $unwrapper;

    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @var ReflectionFactory
     */
    private $factory;

    /**
     * @param Unwrapper              $unwrapper
     * @param Presenter              $presenter
     * @param ReflectionFactory|null $factory
     */
    public function __construct(Unwrapper $unwrapper, Presenter $presenter, ReflectionFactory $factory)
    {
        $this->unwrapper = $unwrapper;
        $this->presenter = $presenter;
        $this->factory   = $factory;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return bool
     */
    public function supports($name, $subject, array $arguments)
    {
        return 'throw' === $name;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return DelayedCall
     */
    public function positiveMatch($name, $subject, array $arguments)
    {
        return $this->getDelayedCall(array($this, 'verifyPositive'), $subject, $arguments);
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return DelayedCall
     */
    public function negativeMatch($name, $subject, array $arguments)
    {
        return $this->getDelayedCall(array($this, 'verifyNegative'), $subject, $arguments);
    }

    /**
     * @param callable           $callable
     * @param array              $arguments
     * @param null|object|string $exception
     *
     * @throws \PhpSpec\Exception\Example\FailureException
     * @throws \PhpSpec\Exception\Example\NotEqualException
     */
    public function verifyPositive($callable, array $arguments, $exception = null)
    {
        $exceptionThrown = null;

        try {
            call_user_func_array($callable, $arguments);
        } catch (\Exception $e) {
            $exceptionThrown = $e;
        } catch (\Throwable $e) {
            $exceptionThrown = $e;
        }

        if (!$exceptionThrown) {
            throw new FailureException('Expected to get exception / throwable, none got.');
        }

        if (null === $exception) {
            return;
        }

        if (!$exceptionThrown instanceof $exception) {
            $format = 'Expected exception of class %s, but got %s.';

            if ($exceptionThrown instanceof \Error) {
                $format = 'Expected exception of class %s, but got %s with the message: "%s"';
            }

            throw new FailureException(
                sprintf(
                    $format,
                    $this->presenter->presentValue($exception),
                    $this->presenter->presentValue($exceptionThrown),
                    $exceptionThrown->getMessage()
                )
            );
        }

        if (is_object($exception)) {
            $exceptionRefl = $this->factory->create($exception);
            foreach ($exceptionRefl->getProperties() as $property) {
                if (in_array($property->getName(), self::$ignoredProperties, true)) {
                    continue;
                }

                $property->setAccessible(true);
                $expected = $property->getValue($exception);
                $actual = $property->getValue($exceptionThrown);

                if (null !== $expected && $actual !== $expected) {
                    throw new NotEqualException(
                        sprintf(
                            'Expected exception `%s` to be %s, but it is %s.',
                            $property->getName(),
                            $this->presenter->presentValue($expected),
                            $this->presenter->presentValue($actual)
                        ), $expected, $actual
                    );
                }
            }
        }
    }

    /**
     * @param callable           $callable
     * @param array              $arguments
     * @param string|null|object $exception
     *
     * @throws \PhpSpec\Exception\Example\FailureException
     */
    public function verifyNegative($callable, array $arguments, $exception = null)
    {
        $exceptionThrown = null;

        try {
            call_user_func_array($callable, $arguments);
        } catch (\Exception $e) {
            $exceptionThrown = $e;
        } catch (\Throwable $e) {
            $exceptionThrown = $e;
        }

        if ($exceptionThrown && null === $exception) {
            throw new FailureException(
                sprintf(
                    'Expected to not throw any exceptions, but got %s.',
                    $this->presenter->presentValue($exceptionThrown)
                )
            );
        }

        if ($exceptionThrown && $exceptionThrown instanceof $exception) {
            $invalidProperties = array();
            if (is_object($exception)) {
                $exceptionRefl = $this->factory->create($exception);
                foreach ($exceptionRefl->getProperties() as $property) {
                    if (in_array($property->getName(), self::$ignoredProperties, true)) {
                        continue;
                    }

                    $property->setAccessible(true);
                    $expected = $property->getValue($exception);
                    $actual = $property->getValue($exceptionThrown);

                    if (null !== $expected && $actual === $expected) {
                        $invalidProperties[] = sprintf(
                            '  `%s`=%s',
                            $property->getName(),
                            $this->presenter->presentValue($expected)
                        );
                    }
                }
            }

            $withProperties = '';
            if (count($invalidProperties) > 0) {
                $withProperties = sprintf(
                    ' with'.PHP_EOL.'%s,'.PHP_EOL,
                    implode(",\n", $invalidProperties)
                );
            }

            throw new FailureException(
                sprintf(
                    'Expected to not throw %s exception%s but got it.',
                    $this->presenter->presentValue($exception),
                    $withProperties
                )
            );
        }
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return 1;
    }

    /**
     * @param callable $check
     * @param mixed    $subject
     * @param array    $arguments
     *
     * @return DelayedCall
     */
    private function getDelayedCall($check, $subject, array $arguments)
    {
        $exception = $this->getException($arguments);
        $unwrapper = $this->unwrapper;

        return new DelayedCall(
            function ($method, $arguments) use ($check, $subject, $exception, $unwrapper) {
                $arguments = $unwrapper->unwrapAll($arguments);

                $methodName = $arguments[0];
                $arguments = isset($arguments[1]) ? $arguments[1] : array();
                $callable = array($subject, $methodName);

                list($class, $methodName) = array($subject, $methodName);
                if (!method_exists($class, $methodName) && !method_exists($class, '__call')) {
                    throw new MethodNotFoundException(
                        sprintf('Method %s::%s not found.', get_class($class), $methodName),
                        $class,
                        $methodName,
                        $arguments
                    );
                }

                return call_user_func($check, $callable, $arguments, $exception);
            }
        );
    }

    /**
     * @param array $arguments
     *
     * @return null|string
     * @throws \PhpSpec\Exception\Example\MatcherException
     */
    private function getException(array $arguments)
    {
        if (0 === count($arguments)) {
            return null;
        }

        if (is_string($arguments[0])) {
            return $arguments[0];
        }

        if (is_object($arguments[0])) {
            if ($arguments[0] instanceof \Throwable) {
                return $arguments[0];
            } elseif ($arguments[0] instanceof \Exception) {
                return $arguments[0];
            }
        }

        throw new MatcherException(
            sprintf(
                "Wrong argument provided in throw matcher.\n".
                "Fully qualified classname or exception instance expected,\n".
                "Got %s.",
                $this->presenter->presentValue($arguments[0])
            )
        );
    }
}
