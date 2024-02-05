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

namespace PhpSpec\Wrapper\Subject;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Matcher\Matcher;
use PhpSpec\Wrapper\Subject\Expectation\ConstructorDecorator;
use PhpSpec\Wrapper\Subject\Expectation\DispatcherDecorator;
use PhpSpec\Wrapper\Subject\Expectation\Expectation;
use PhpSpec\Wrapper\Subject\Expectation\Negative;
use PhpSpec\Wrapper\Subject\Expectation\NegativeThrow;
use PhpSpec\Wrapper\Subject\Expectation\NegativeTrigger;
use PhpSpec\Wrapper\Subject\Expectation\Positive;
use PhpSpec\Wrapper\Subject\Expectation\PositiveThrow;
use PhpSpec\Wrapper\Subject\Expectation\PositiveTrigger;
use PhpSpec\Wrapper\Subject\Expectation\ThrowExpectation;
use PhpSpec\Wrapper\Subject\Expectation\UnwrapDecorator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Wrapper\Unwrapper;

class ExpectationFactory
{
    public function __construct(
        private ExampleNode $example,
        private EventDispatcherInterface $dispatcher,
        private MatcherManager $matchers
    )
    {
    }

    public function create(string $expectation, mixed $subject, array $arguments = array()): Expectation
    {
        if (0 === strpos($expectation, 'shouldNot')) {
            return $this->createNegative(lcfirst(substr($expectation, 9)), $subject, $arguments);
        }

        if (0 === strpos($expectation, 'should')) {
            return $this->createPositive(lcfirst(substr($expectation, 6)), $subject, $arguments);
        }

        throw new \RuntimeException('Could not create match');
    }

    private function createPositive(string $name, mixed $subject, array $arguments = array()): Expectation
    {
        if (strtolower($name) === 'throw') {
            return $this->createDecoratedExpectation(PositiveThrow::class, $name, $subject, $arguments);
        }

        if (strtolower($name) === 'trigger') {
            return $this->createDecoratedExpectation(PositiveTrigger::class, $name, $subject, $arguments);
        }

        return $this->createDecoratedExpectation(Positive::class, $name, $subject, $arguments);
    }

    private function createNegative(string $name, mixed $subject, array $arguments = array()): Expectation
    {
        if (strtolower($name) === 'throw') {
            return $this->createDecoratedExpectation(NegativeThrow::class, $name, $subject, $arguments);
        }

        if (strtolower($name) === 'trigger') {
            return $this->createDecoratedExpectation(NegativeTrigger::class, $name, $subject, $arguments);
        }

        return $this->createDecoratedExpectation(Negative::class, $name, $subject, $arguments);
    }

    /** @param class-string<Expectation> $expectationName */
    private function createDecoratedExpectation(string $expectationName, string $name, mixed $subject, array $arguments): Expectation
    {
        $matcher = $this->findMatcher($name, $subject, $arguments);
        $expectation = new $expectationName($matcher);

        if ($expectation instanceof ThrowExpectation) {
            return $expectation;
        }

        return $this->decoratedExpectation($expectation, $matcher);
    }

    private function findMatcher(string $name, mixed $subject, array $arguments = array()): Matcher
    {
        $unwrapper = new Unwrapper();
        $arguments = $unwrapper->unwrapAll($arguments);

        return $this->matchers->find($name, $subject, $arguments);
    }

    private function decoratedExpectation(Expectation $expectation, Matcher $matcher): ConstructorDecorator
    {
        $dispatcherDecorator = new DispatcherDecorator($expectation, $this->dispatcher, $matcher, $this->example);
        $unwrapperDecorator = new UnwrapDecorator($dispatcherDecorator, new Unwrapper());
        $constructorDecorator = new ConstructorDecorator($unwrapperDecorator);

        return $constructorDecorator;
    }
}
