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

final class TraversableKeyMatcher extends BasicMatcher
{
    public function __construct(
        private Presenter $presenter
    )
    {
    }

    public function supports(string $name, mixed $subject, array $arguments): bool
    {
        return 'haveKey' === $name
            && 1 === \count($arguments)
            && $subject instanceof \Traversable
        ;
    }

    public function getPriority(): int
    {
        return 101;
    }

    protected function matches(mixed $subject, array $arguments): bool
    {
        foreach ($subject as $key => $value) {
            if ($key === $arguments[0]) {
                return true;
            }
        }

        return false;
    }

    protected function getFailureException(string $name, mixed $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Expected %s to have %s key, but it does not.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0])
        ));
    }

    protected function getNegativeFailureException(string $name, mixed $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Expected %s not to have %s key, but it does.',
            $this->presenter->presentValue($subject),
            $this->presenter->presentValue($arguments[0])
        ));
    }
}
