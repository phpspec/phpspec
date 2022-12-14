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
use PhpSpec\Wrapper\DelayedCall;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Exception;

final class DispatcherDecorator extends Decorator implements Expectation
{
    public function __construct(
        Expectation $expectation,
        private EventDispatcherInterface $dispatcher,
        private Matcher $matcher,
        private ExampleNode $example
    ) {
        $this->setExpectation($expectation);
    }

    /**
     * @throws \Exception
     * @throws FailureException
     * @throws \Exception
     */
    public function match(string $alias, mixed $subject, array $arguments = array()): mixed
    {
        $this->dispatcher->dispatch(
            new ExpectationEvent($this->example, $this->matcher, $subject, $alias, $arguments),
            'beforeExpectation'
        );

        try {
            $result = $this->getExpectation()->match($alias, $subject, $arguments);
            $this->dispatcher->dispatch(
                new ExpectationEvent(
                    $this->example,
                    $this->matcher,
                    $subject,
                    $alias,
                    $arguments,
                    ExpectationEvent::PASSED
                ),
                'afterExpectation'
            );
        } catch (FailureException $e) {
            $this->dispatcher->dispatch(
                new ExpectationEvent(
                    $this->example,
                    $this->matcher,
                    $subject,
                    $alias,
                    $arguments,
                    ExpectationEvent::FAILED,
                    $e
                ),
                'afterExpectation'
            );

            throw $e;
        } catch (Exception $e) {
            $this->dispatcher->dispatch(
                new ExpectationEvent(
                    $this->example,
                    $this->matcher,
                    $subject,
                    $alias,
                    $arguments,
                    ExpectationEvent::BROKEN,
                    $e
                ),
                'afterExpectation'
            );

            throw $e;
        }

        return $result;
    }
}
