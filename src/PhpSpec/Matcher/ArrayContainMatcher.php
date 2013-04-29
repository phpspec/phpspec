<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;

class ArrayContainMatcher extends BasicMatcher
{
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return 'contain' === $name
            && 1 == count($arguments)
            && is_array($subject)
        ;
    }

    protected function matches($subject, array $arguments)
    {
        return in_array($arguments[0], $subject);
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to contain %s, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Not expected %s to contain %s, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }
}
