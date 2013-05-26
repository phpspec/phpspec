<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Example\NotEqualException;

class IdentityMatcher extends BasicMatcher
{
    private static $keywords = array(
        'return',
        'be',
        'equal',
        'beEqualTo'
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
       return $subject === $arguments[0];
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new NotEqualException(sprintf(
            'Expected %s, but got %s.',
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($subject)
        ), $arguments[0], $subject);
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Did not expect %s, but got one.',
            $this->presenter->presentValue($subject)
        ));
    }
}
