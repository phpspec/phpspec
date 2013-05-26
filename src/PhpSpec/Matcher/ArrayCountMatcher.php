<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;

class ArrayCountMatcher extends BasicMatcher
{
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return 'haveCount' === $name
            && 1 == count($arguments)
            && (is_array($subject) || $subject instanceof \Countable)
        ;
    }

    protected function matches($subject, array $arguments)
    {
        return $arguments[0] === count($subject);
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to have %s items, but got %s.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString(intval($arguments[0])),
            $this->presenter->presentString(count($subject))
        ));
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s not to have %s items, but got it.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString(intval($arguments[0]))
        ));
    }
}
