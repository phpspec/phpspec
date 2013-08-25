<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Wrapper\DelayedCall;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Event\ExpectationEvent;

use PhpSpec\Exception\Example\FailureException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Expectation
{
    const POSITIVE = 'positive';
    const NEGATIVE = 'negative';

    private $subject;
    private $unwrapper;
    private $matchers;
    private $example;
    private $dispatcher;

    public function __construct($subject, MatcherManager $matchers, ExampleNode $example, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher)
    {
        $this->subject   = $subject;
        $this->unwrapper = $unwrapper;
        $this->matchers  = $matchers;
        $this->example   = $example;
        $this->dispatcher = $dispatcher;
    }

    public function should($name = null, array $arguments = array())
    {
        return $this->findMatcherAndMatch(
            __METHOD__, self::POSITIVE, $name, $arguments
        );
    }

    public function shouldNot($name = null, array $arguments = array())
    {
        return $this->findMatcherAndMatch(
            __METHOD__, self::NEGATIVE, $name, $arguments
        );
    }

    private function findMatcherAndMatch($method, $matchingType, $name, $arguments)
    {
        if (null === $name) {
            return new DelayedCall(array($this, $method));
        }

        $expectationMatch = $this->createExpectationMatch($matchingType);
        $matcher = $this->findMatcher($name, $arguments);

        return $this->performMatch($expectationMatch, $matcher, $name, $arguments);
    }

    private function createExpectationMatch($expectation)
    {
        $matches = array(
            self::POSITIVE => function ($name, $arguments, $subject, $matcher) {
                return $matcher->positiveMatch($name, $subject, $arguments);
            },
            self::NEGATIVE => function ($name, $arguments, $subject, $matcher) {
                return $matcher->negativeMatch($name, $subject, $arguments);
            }
        );

        return $matches[$expectation];
    }

    private function performMatch($expectationMatch, $matcher, $name, array $arguments = array())
    {
        $subject = $this->subject;
        $arguments = $this->unwrapper->unwrapAll($arguments);

        $this->dispatcher->dispatch('beforeExpectation',
            new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments)
        );

        try {
            $matchResult = $expectationMatch($name, $arguments, $subject, $matcher);
        } catch (FailureException $exception) {
            $this->dispatcher->dispatch('afterExpectation',
                new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments, ExpectationEvent::FAILED, $exception)
            );

            throw $exception;
        } catch (RuntimeException $exception) {
            $this->dispatcher->dispatch('afterExpectation',
                new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments, ExpectationEvent::BROKEN, $exception)
            );

            throw $exception;
        }

        $this->dispatcher->dispatch('afterExpectation',
            new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments, ExpectationEvent::PASSED)
        );

        return $matchResult;
    }

    private function findMatcher($name, array $arguments = array())
    {
        $arguments = $this->unwrapper->unwrapAll($arguments);
        return $this->matchers->find($name, $this->subject, $arguments);
    }
}
