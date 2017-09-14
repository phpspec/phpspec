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
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

final class ObjectStateMatcher implements Matcher
{
    /**
     * @var string
     */
    private static $regex = '/(be|have)(.+)/';
    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @param Presenter $presenter
     */
    public function __construct(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return bool
     */
    public function supports(string $name, $subject, array $arguments): bool
    {
        return \is_object($subject) && !is_callable($subject)
            && (0 === strpos($name, 'be') || 0 === strpos($name, 'have'))
        ;
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @throws \PhpSpec\Exception\Example\FailureException
     * @throws \PhpSpec\Exception\Fracture\MethodNotFoundException
     */
    public function positiveMatch(string $name, $subject, array $arguments)
    {
        preg_match(self::$regex, $name, $matches);
        $method   = ('be' === $matches[1] ? 'is' : 'has').ucfirst($matches[2]);
        $callable = array($subject, $method);

        if (!method_exists($subject, $method)) {
            throw new MethodNotFoundException(sprintf(
                'Method %s not found.',
                $this->presenter->presentValue($callable)
            ), $subject, $method, $arguments);
        }

        if (true !== $result = \call_user_func_array($callable, $arguments)) {
            throw $this->getFailureExceptionFor($callable, true, $result);
        }
    }

    /**
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @throws \PhpSpec\Exception\Example\FailureException
     * @throws \PhpSpec\Exception\Fracture\MethodNotFoundException
     */
    public function negativeMatch(string $name, $subject, array $arguments)
    {
        preg_match(self::$regex, $name, $matches);
        $method   = ('be' === $matches[1] ? 'is' : 'has').ucfirst($matches[2]);
        $callable = array($subject, $method);

        if (!method_exists($subject, $method)) {
            throw new MethodNotFoundException(sprintf(
                'Method %s not found.',
                $this->presenter->presentValue($callable)
            ), $subject, $method, $arguments);
        }

        if (false !== $result = \call_user_func_array($callable, $arguments)) {
            throw $this->getFailureExceptionFor($callable, false, $result);
        }
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 50;
    }

    /**
     * @param callable $callable
     * @param Boolean  $expectedBool
     * @param Boolean  $result
     *
     * @return FailureException
     */
    private function getFailureExceptionFor(callable $callable, bool $expectedBool, bool $result): FailureException
    {
        return new FailureException(sprintf(
            "Expected %s to return %s, but got %s.",
            $this->presenter->presentValue($callable),
            $this->presenter->presentValue($expectedBool),
            $this->presenter->presentValue($result)
        ));
    }
}
