<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Event\ExpectationEvent;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\MatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use RuntimeException;

class DispatcherDecorator implements ExpectationInterface
{
    private $expectation;
    private $dispatcher;
    private $matcher;
    private $example;

    public function __construct(ExpectationInterface $expectation, EventDispatcherInterface $dispatcher, MatcherInterface $matcher, ExampleNode $example)
    {
        $this->expectation = $expectation;
        $this->dispatcher = $dispatcher;
        $this->matcher = $matcher;
        $this->example = $example;
    }

    public function match($alias, $subject, array $arguments = array())
    {
        $this->dispatcher->dispatch(
            'beforeExpectation',
            new ExpectationEvent($this->example, $this->matcher, $subject, $alias, $arguments)
        );

        try {
            $result = $this->getExpectation()->match($alias, $subject, $arguments);
            $this->dispatcher->dispatch(
                'afterExpectation',
                new ExpectationEvent($this->example, $this->matcher, $subject, $alias, $arguments, ExpectationEvent::PASSED)
            );
        } catch (FailureException $e) {
            $this->dispatcher->dispatch(
                'afterExpectation',
                new ExpectationEvent($this->example, $this->matcher, $subject, $alias, $arguments, ExpectationEvent::FAILED, $e)
            );

            throw $e;
        } catch (RuntimeException $e) {
            $this->dispatcher->dispatch(
                'afterExpectation',
                new ExpectationEvent($this->example, $this->matcher, $subject, $alias, $arguments, ExpectationEvent::BROKEN, $e)
            );

            throw $e;
        }

        return $result;
    }

    public function getExpectation()
    {
        return $this->expectation;
    }
}
