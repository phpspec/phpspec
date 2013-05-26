<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;

class StringEndMatcher extends BasicMatcher
{
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return 'endWith' === $name
            && is_string($subject)
            && 1 == count($arguments)
        ;
    }

    protected function matches($subject, array $arguments)
    {
        return $arguments[0] === substr($subject, 0 - strlen($arguments[0]));
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to end with %s, but it does not.',
            $this->presenter->presentString($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s not to end with %s, but it does.',
            $this->presenter->presentString($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }
}
