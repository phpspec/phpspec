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

final class TraversableKeyValueMatcher extends BasicMatcher
{
    public function __construct(private Presenter $presenter)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $name, mixed$subject, array $arguments): bool
    {
        return 'haveKeyWithValue' === $name
            && 2 === \count($arguments)
            && $subject instanceof \Traversable
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): int
    {
        return 101;
    }

    /**
     * {@inheritdoc}
     */
    protected function matches(mixed $subject, array $arguments): bool
    {
        foreach ($subject as $key => $value) {
            if ($key === $arguments[0] && $value === $arguments[1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFailureException(string $name, mixed $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Expected %s to have an element with %s key and %s value, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($arguments[1])
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function getNegativeFailureException(string $name, mixed $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Expected %s not to have an element with %s key and %s value, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($arguments[1])
        ));
    }
}
