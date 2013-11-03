<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\Wrapper\Subject\Expectation\DispatcherDecorator;
use PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;

class ExpectationFactory
{
    private $example;
    private $dispatcher;
    private $matchers;

    public function __construct(ExampleNode $example, EventDispatcherInterface $dispatcher, MatcherManager $matchers)
    {
        $this->example = $example;
        $this->dispatcher = $dispatcher;
        $this->matchers = $matchers;
    }

    public function create($expectaction, $subject, array $arguments = array())
    {
        if (0 === strpos($expectaction, 'shouldNot')) {
            if (0 === strpos($expectaction, 'shouldNotThrow')) {
                return $this->createNegativeException($subject, $arguments);
            }
            return $this->createNegative(lcfirst(substr($expectaction, 9)), $subject, $arguments);
        }

        if (0 === strpos($expectaction, 'should')) {
            if (0 === strpos($expectaction, 'shouldThrow')) {
                return $this->createPositiveException($subject, $arguments);
            }
            return $this->createPositive(lcfirst(substr($expectaction, 6)), $subject, $arguments);
        }
    }

    private function createPositive($name, $subject, array $arguments = array())
    {
        $matcher = $this->findMatcher($name, $subject, $arguments);
        return $this->decorateWithDispatcher(new Expectation\Positive($matcher), $matcher);
    }

    private function createNegative($name, $subject, array $arguments = array())
    {
        $matcher = $this->findMatcher($name, $subject, $arguments);
        return $this->decorateWithDispatcher(new Expectation\Negative($matcher), $matcher);
    }

    private function createPositiveException($subject, array $arguments = array())
    {
        $matcher = $this->findMatcher('throw', $subject, $arguments);
        return $this->decorateWithDispatcher(new Expectation\PositiveException($matcher), $matcher);
    }

    private function createNegativeException($subject, array $arguments = array())
    {
        $matcher = $this->findMatcher('throw', $subject, $arguments);
        return $this->decorateWithDispatcher(new Expectation\NegativeException($matcher), $matcher);
    }

    private function findMatcher($name, $subject, array $arguments = array())
    {
        $unwrapper = new Unwrapper;
        $arguments = $unwrapper->unwrapAll($arguments);
        return $this->matchers->find($name, $subject, $arguments);
    }

    private function decorateWithDispatcher(ExpectationInterface $expectation, MatcherInterface $matcher)
    {
        return new DispatcherDecorator($expectation, $this->dispatcher, $matcher, $this->example);
    }
}
