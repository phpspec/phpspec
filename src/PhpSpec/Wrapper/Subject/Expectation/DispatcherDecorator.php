<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Event\ExpectationEvent;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\Matcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Exception;

final class DispatcherDecorator extends Decorator implements Expectation
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;
    /**
     * @var Matcher
     */
    private $matcher;
    /**
     * @var ExampleNode
     */
    private $example;

    /**
     * @param Expectation     $expectation
     * @param EventDispatcherInterface $dispatcher
     * @param Matcher         $matcher
     * @param ExampleNode              $example
     */
    public function __construct(
        Expectation $expectation,
        EventDispatcherInterface $dispatcher,
        Matcher $matcher,
        ExampleNode $example
    ) {
        $this->setExpectation($expectation);
        $this->dispatcher = $dispatcher;
        $this->matcher = $matcher;
        $this->example = $example;
    }

    /**
     * @param  string  $alias
     * @param  mixed   $subject
     * @param  array   $arguments
     * @return boolean
     *
     * @throws \Exception
     * @throws \PhpSpec\Exception\Example\FailureException
     * @throws \Exception
     */
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
                new ExpectationEvent(
                    $this->example,
                    $this->matcher,
                    $subject,
                    $alias,
                    $arguments,
                    ExpectationEvent::PASSED
                )
            );
        } catch (FailureException $e) {
            $this->dispatcher->dispatch(
                'afterExpectation',
                new ExpectationEvent(
                    $this->example,
                    $this->matcher,
                    $subject,
                    $alias,
                    $arguments,
                    ExpectationEvent::FAILED,
                    $e
                )
            );

            throw $e;
        } catch (Exception $e) {
            $this->dispatcher->dispatch(
                'afterExpectation',
                new ExpectationEvent(
                    $this->example,
                    $this->matcher,
                    $subject,
                    $alias,
                    $arguments,
                    ExpectationEvent::BROKEN,
                    $e
                )
            );

            throw $e;
        }

        return $result;
    }
}
