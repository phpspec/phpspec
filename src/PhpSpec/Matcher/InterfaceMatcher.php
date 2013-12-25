<?php

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\PresenterInterface;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Exception\Fracture\InterfaceNotFoundException;
use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;

class InterfaceMatcher extends BasicMatcher
{
    private static $keywords = array(
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
        if (!interface_exists($arguments[0])) {
            throw new InterfaceNotFoundException(sprintf(
                'Interface %s not found',
                $this->presenter->presentString($arguments[0])
            ), $subject, $arguments[0]);
        }

        return (null !== $subject) && $this->checkInterfaceImplementation($subject, $arguments[0]);
    }

    protected function checkInterfaceImplementation($subject, $interface)
    {
        return in_array($interface, class_implements($subject));
    }

    protected function getFailureException($name, $subject, array $arguments)
    {
        return new InterfaceNotImplementedException(sprintf(
            'Expected %s to implement %s, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ), $subject, $arguments[0]);
    }

    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to not implement %s, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentString($arguments[0])
        ));
    }
}
