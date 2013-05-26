<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;

class CallbackMatcher extends BasicMatcher
{
    private $name;
    private $callback;
    private $presenter;

    public function __construct($name, $callback, PresenterInterface $presenter)
    {
        $this->name      = $name;
        $this->callback  = $callback;
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return $name === $this->name;
    }

    protected function matches($subject, array $arguments)
    {
        array_unshift($arguments, $subject);

        return (Boolean) call_user_func_array($this->callback, $arguments);
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            '%s expected to %s(%s), but it is not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($name),
            implode(', ', array_map(array($this->presenter, 'presentValue'), $arguments))
        ));
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            '%s not expected to %s(%s), but it did.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($name),
            implode(', ', array_map(array($this->presenter, 'presentValue'), $arguments))
        ));
    }
}
