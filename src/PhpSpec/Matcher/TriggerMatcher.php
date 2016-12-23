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

use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\DelayedCall;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

final class TriggerMatcher implements Matcher
{
    /**
     * @var Unwrapper
     */
    private $unwrapper;

    /**
     * @param Unwrapper $unwrapper
     */
    public function __construct(Unwrapper $unwrapper)
    {
        $this->unwrapper = $unwrapper;
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
        return 'trigger' === $name;
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
     * @param callable    $callable
     * @param array       $arguments
     * @param int|null    $level
     * @param string|null $message
     *
     * @throws \PhpSpec\Exception\Example\FailureException
     */
    public function verifyPositive(callable $callable, array $arguments, $level = null, $message = null)
    {
        $triggered = 0;

        $prevHandler = set_error_handler(function ($type, $str, $file, $line, $context) use (&$prevHandler, $level, $message, &$triggered) {
            if (null !== $level && $level !== $type) {
                return null !== $prevHandler && call_user_func($prevHandler, $type, $str, $file, $line, $context);
            }

            if (null !== $message && false === strpos($str, $message)) {
                return null !== $prevHandler && call_user_func($prevHandler, $type, $str, $file, $line, $context);
            }

            ++$triggered;
        });

        call_user_func_array($callable, $arguments);

        restore_error_handler();

        if ($triggered === 0) {
            throw new FailureException('Expected to trigger errors, but got none.');
        }
    }

    /**
     * @param callable    $callable
     * @param array       $arguments
     * @param int|null    $level
     * @param string|null $message
     *
     * @throws \PhpSpec\Exception\Example\FailureException
     */
    public function verifyNegative(callable $callable, array $arguments, $level = null, $message = null)
    {
        $triggered = 0;

        $prevHandler = set_error_handler(function ($type, $str, $file, $line, $context) use (&$prevHandler, $level, $message, &$triggered) {
            if (null !== $level && $level !== $type) {
                return null !== $prevHandler && call_user_func($prevHandler, $type, $str, $file, $line, $context);
            }

            if (null !== $message && false === strpos($str, $message)) {
                return null !== $prevHandler && call_user_func($prevHandler, $type, $str, $file, $line, $context);
            }

            ++$triggered;
        });

        call_user_func_array($callable, $arguments);

        restore_error_handler();

        if ($triggered > 0) {
            throw new FailureException(
                sprintf(
                    'Expected to not trigger errors, but got %d.',
                    $triggered
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
        $unwrapper = $this->unwrapper;
        list($level, $message) = $this->unpackArguments($arguments);

        return new DelayedCall(
            function ($method, $arguments) use ($check, $subject, $level, $message, $unwrapper) {
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

                return call_user_func($check, $callable, $arguments, $level, $message);
            }
        );
    }

    /**
     * @return array
     */
    private function unpackArguments(array $arguments)
    {
        $count = count($arguments);

        if (0 === $count) {
            return array(null, null);
        }

        if (1 === $count) {
            return array($arguments[0], null);
        }

        if (2 !== $count) {
            throw new MatcherException(
                sprintf(
                    "Wrong argument count provided in trigger matcher.\n".
                    "Up to two arguments expected,\n".
                    "Got %d.",
                    $count
                )
            );
        }

        return array($arguments[0], $arguments[1]);
    }
}
