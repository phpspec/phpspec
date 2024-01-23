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
use PhpSpec\Exception\Example\NotEqualException;

final class IdentityMatcher extends BasicMatcher
{
    private static array $keywords = [
        'return',
        'be',
        'equal',
        'beEqualTo'
    ];

    public function __construct(
        private Presenter $presenter
    )
    {
    }

    public function supports(string $name, mixed $subject, array $arguments): bool
    {
        return \in_array($name, self::$keywords)
            && 1 == \count($arguments)
        ;
    }

    protected function matches(mixed $subject, array $arguments): bool
    {
        return $subject === $arguments[0];
    }

    protected function getFailureException(string $name, mixed $subject, array $arguments): FailureException
    {
        return new NotEqualException(sprintf(
            'Expected %s, but got %s.',
            $this->presenter->presentValue($arguments[0]),
            $this->presenter->presentValue($subject)
        ), $arguments[0], $subject);
    }

    protected function getNegativeFailureException(string $name, mixed $subject, array $arguments): FailureException
    {
        return new FailureException(sprintf(
            'Did not expect %s, but got one.',
            $this->presenter->presentValue($subject)
        ));
    }
}
