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

final class TraversableCountMatcher implements Matcher
{
    const LESS_THAN = 0;
    const EQUAL = 1;
    const MORE_THAN = 2;

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
        return 'haveCount' === $name
            && 1 === count($arguments)
            && $subject instanceof \Traversable
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function positiveMatch($name, $subject, array $arguments)
    {
        $countDifference = $this->countDifference($subject, (int) $arguments[0]);

        if (self::EQUAL !== $countDifference) {
            throw new FailureException(sprintf(
                'Expected %s to have %s items, but got %s than that.',
                $this->presenter->presentValue($subject),
                $this->presenter->presentString((int) $arguments[0]),
                self::MORE_THAN === $countDifference ? 'more' : 'less'
            ));
        }

        return $subject;
    }

    /**
     * {@inheritdoc}
     */
    public function negativeMatch($name, $subject, array $arguments)
    {
        $count = $this->countDifference($subject, (int) $arguments[0]);

        if (self::EQUAL === $count) {
            throw new FailureException(sprintf(
                'Expected %s not to have %s items, but got it.',
                $this->presenter->presentValue($subject),
                $this->presenter->presentString((int) $arguments[0])
            ));
        }

        return $subject;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return 100;
    }

    /**
     * @param \Traversable $subject
     * @param int $expected
     *
     * @return int self::*
     */
    private function countDifference(\Traversable $subject, $expected)
    {
        $count = 0;
        foreach ($subject as $value) {
            ++$count;

            if ($count > $expected) {
                return self::MORE_THAN;
            }
        }

        return $count === $expected ? self::EQUAL : self::LESS_THAN;
    }
}
