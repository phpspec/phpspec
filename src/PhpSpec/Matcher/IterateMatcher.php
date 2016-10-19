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

final class IterateMatcher implements Matcher
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
    public function positiveMatch($name, $subject, array $arguments)
    {
        $expected = $arguments[0];
        if (is_array($expected)) {
            $expected = new \ArrayIterator($expected);
        }

        $expectedIterator = new \IteratorIterator($expected);

        $count = 0;
        $expectedIterator->rewind();
        foreach ($subject as $subjectKey => $subjectValue) {
            if (!$expectedIterator->valid()) {
                throw new FailureException('Expect subject to have the same count than matched value, but it has more records.');
            }

            if ($subjectKey !== $expectedIterator->key() || $subjectValue !== $expectedIterator->current()) {
                throw new FailureException(sprintf(
                    'Expected subject to have record #%d with key %s and value %s, but got key %s and value %s.',
                    $count,
                    $this->presenter->presentValue($expectedIterator->key()),
                    $this->presenter->presentValue($expectedIterator->current()),
                    $this->presenter->presentValue($subjectKey),
                    $this->presenter->presentValue($subjectValue)
                ));
            }

            $expectedIterator->next();
            ++$count;
        }

        if ($expectedIterator->valid()) {
            throw new FailureException('Expect subject to have the same count than matched value, but it has less records.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function negativeMatch($name, $subject, array $arguments)
    {
        try {
            $this->positiveMatch($name, $subject, $arguments);
        } catch (FailureException $exception) {
            return;
        }

        throw new FailureException('Expected subject not to iterate the same as matched value, but it does.');
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 100;
    }
}
