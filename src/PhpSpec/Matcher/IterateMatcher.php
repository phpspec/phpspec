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

namespace PhpSpec\Matcher;

use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Exception\Example\FailureException;
use ArrayAccess;

final class IterateMatcher extends BasicMatcher
{
    /**
     * @var Presenter
     */
    private $presenter;

    /**
     * @param Presenter $presenter
     */
    public function __construct(Presenter $presenter)
    {
        $this->presenter = $presenter;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($name, $subject, array $arguments)
    {
        return 'iterate' === $name
            && 1 === count($arguments)
            && $subject instanceof \Traversable
            && ($arguments[0] instanceof \Traversable || is_array($arguments[0]))
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function matches($subject, array $arguments)
    {
        $expected = $arguments[0];
        if (is_array($expected)) {
            $expected = new \ArrayIterator($expected);
        }

        $expectedIterator = new \IteratorIterator($expected);

        $expectedIterator->rewind();
        foreach ($subject as $subjectKey => $subjectValue) {
            if (!$expectedIterator->valid()) {
                return false;
            }

            if ($subjectKey !== $expectedIterator->key() || $subjectValue !== $expectedIterator->current()) {
                return false;
            }

            $expectedIterator->next();
        }

        return !$expected->valid();
    }

    /**
     * {@inheritdoc}
     */
    protected function getFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to iterate the same as %s, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0])
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function getNegativeFailureException($name, $subject, array $arguments)
    {
        return new FailureException(sprintf(
            'Expected %s to iterate the same as %s, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0])
        ));
    }
}
