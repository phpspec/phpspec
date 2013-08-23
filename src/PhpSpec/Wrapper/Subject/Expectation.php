<?php

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Wrapper\DelayedCall;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Subject;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Event\ExpectationEvent;

use PhpSpec\Exception\Example\FailureException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Expectation
{
    private $subject;
    private $unwrapper;
    private $matchers;
    private $example;
    private $dispatcher;

    public function __construct(Subject $subject, MatcherManager $matchers, ExampleNode $example, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher)
    {
        $this->subject   = $subject;
        $this->unwrapper = $unwrapper;
        $this->matchers  = $matchers;
        $this->example   = $example;
        $this->dispatcher = $dispatcher;
    }

    public function should($name = null, array $arguments = array())
    {
        if (null === $name) {
            return new DelayedCall(array($this, __METHOD__));
        }

        $subject   = $this->unwrapper->unwrapOne($this->subject);
        $arguments = $this->unwrapper->unwrapAll($arguments);
        $matcher   = $this->matchers->find($name, $subject, $arguments);

        $this->dispatcher->dispatch('beforeExpectation',
            new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments)
        );

        try {
            $matchResult = $matcher->positiveMatch($name, $subject, $arguments);
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

    public function shouldNot($name, array $arguments = array())
    {
        if (null === $name) {
            return new DelayedCall(array($this, __METHOD__));
        }

        $subject   = $this->unwrapper->unwrapOne($this->subject);
        $arguments = $this->unwrapper->unwrapAll($arguments);
        $matcher   = $this->matchers->find($name, $subject, $arguments);

        $this->dispatcher->dispatch('beforeExpectation',
            new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments)
        );

        try {
            $matchResult = $matcher->negativeMatch($name, $subject, $arguments);
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
            new ExpectationEvent($this->example, $matcher, $subject, $name, $arguments)
        );

        return $matchResult;
    }
}
