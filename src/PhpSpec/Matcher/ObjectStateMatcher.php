<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Fracture\MethodNotFoundException;

class ObjectStateMatcher implements MatcherInterface
{
    private static $regex = '/(be|have)(.+)/';
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return is_object($subject)
            && (0 === strpos($name, 'be') || 0 === strpos($name, 'have'))
        ;
    }

    public function positiveMatch($name, $subject, array $arguments)
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

        if (true !== $result = call_user_func_array($callable, $arguments)) {
            throw $this->getFailureExceptionFor($callable, true, $result);
        }
    }

    public function negativeMatch($name, $subject, array $arguments)
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

        if (false !== $result = call_user_func_array($callable, $arguments)) {
            throw $this->getFailureExceptionFor($callable, false, $result);
        }
    }

    public function getPriority()
    {
        return 50;
    }

    private function getFailureExceptionFor($callable, $expectedBool, $result)
    {
        return new FailureException(sprintf(
            "Expected %s to return %s, but got %s.",
            $this->presenter->presentValue($callable),
            $this->presenter->presentValue($expectedBool),
            $this->presenter->presentValue($result)
        ));
    }
}
