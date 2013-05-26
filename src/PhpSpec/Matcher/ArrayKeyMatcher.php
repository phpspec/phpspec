<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;

use ArrayAccess;

class ArrayKeyMatcher extends BasicMatcher
{
    private $presenter;

    public function __construct(PresenterInterface $presenter)
    {
        $this->presenter = $presenter;
    }

    public function supports($name, $subject, array $arguments)
    {
        return 'haveKey' === $name
            && 1 == count($arguments)
            && (is_array($subject) || $subject instanceof ArrayAccess)
        ;
    }

    protected function matches($subject, array $arguments)
    {
        return isset($subject[$arguments[0]]);
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to have %s key, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s not to have %s key, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }
}
