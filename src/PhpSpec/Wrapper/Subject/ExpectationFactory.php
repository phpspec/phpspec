<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use PhpSpec\Wrapper\Subject\Expectation\ConstructorDecorator;
use PhpSpec\Wrapper\Subject\Expectation\DispatcherDecorator;
use PhpSpec\Wrapper\Subject\Expectation\ExpectationInterface;
use PhpSpec\Wrapper\Subject\Expectation\UnwrapDecorator;
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

    public function create($expectation, $subject, array $arguments = array())
    {
        if (0 === strpos($expectation, 'shouldNot')) {
            return $this->createNegative(lcfirst(substr($expectation, 9)), $subject, $arguments);
        }

        if (0 === strpos($expectation, 'should')) {
            return $this->createPositive(lcfirst(substr($expectation, 6)), $subject, $arguments);
        }
    }

    private function createPositive($name, $subject, array $arguments = array())
    {
        if (strtolower($name) === 'throw') {
            return $this->createDecoratedExpectation("PositiveThrow", $name, $subject, $arguments);
        }
        return $this->createDecoratedExpectation("Positive", $name, $subject, $arguments);
    }

    private function createNegative($name, $subject, array $arguments = array())
    {
        if (strtolower($name) === 'throw') {
            return $this->createDecoratedExpectation("NegativeThrow", $name, $subject, $arguments);
        }
        return $this->createDecoratedExpectation("Negative", $name, $subject, $arguments);
    }

    private function createDecoratedExpectation($expectation, $name, $subject, array $arguments)
    {
        $matcher = $this->findMatcher($name, $subject, $arguments);
        $expectation = "\\PhpSpec\\Wrapper\\Subject\\Expectation\\" . $expectation;

        $expectation = new $expectation($matcher);

        if ($expectation instanceof Expectation\ThrowExpectation) {
            return $expectation;
        }
        return $this->decoratedExpectation($expectation, $matcher);
    }

    private function findMatcher($name, $subject, array $arguments = array())
    {
        $unwrapper = new Unwrapper;
        $arguments = $unwrapper->unwrapAll($arguments);
        return $this->matchers->find($name, $subject, $arguments);
    }

    private function decoratedExpectation(ExpectationInterface $expectation, MatcherInterface $matcher)
    {
        $dispatcherDecorator = new DispatcherDecorator($expectation, $this->dispatcher, $matcher, $this->example);
        $unwrapperDecorator = new UnwrapDecorator($dispatcherDecorator, new Unwrapper);
        $constructorDecorator = new ConstructorDecorator($unwrapperDecorator, new Unwrapper);

        return $constructorDecorator;
    }
}
