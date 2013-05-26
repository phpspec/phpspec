<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;

class TypeMatcher extends BasicMatcher
{
    private static $keywords = array(
        'beAnInstanceOf',
        'returnAnInstanceOf',
        'haveType',
        'implement'
    );
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return in_array($name, self::$keywords)
            && 1 == count($arguments)
        ;
    }

    protected function matches($subject, array $arguments)
    {
        return (null !== $subject) && ($subject instanceof $arguments[0]);
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected an instance of %s, but got %s.',
            $this->presenter->presentString($arguments[0]),
            $this->presenter->presentValue($subject)
        ));
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Did not expect instance of %s, but got %s.',
            $this->presenter->presentString($arguments[0]),
            $this->presenter->presentValue($subject)
        ));
    }
}
