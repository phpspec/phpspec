<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Mock;

use LogicException;
use PhpSpec\ObjectName;
use PhpSpec\ProjectRoot;

/**
 * A call nothing declared: one on a double, made from outside the spec code,
 * or one on what a double answered to a call nobody stubbed, made anywhere.
 */
final class UnexpectedCallException extends LogicException
{
    /**
     * @param array<int, mixed> $arguments
     */
    public static function to(string $class, string $method, array $arguments, string $file, int $line): self
    {
        return new self(sprintf(
            '%s::%s(%s) was called but not expected, at %s:%d.',
            ltrim($class, '\\'),
            $method,
            self::describeAll($arguments),
            ProjectRoot::here()->relative($file),
            $line,
        ));
    }

    /**
     * What a double answered to a call nobody stubbed is there to be handed
     * along, never used.
     *
     * @param array<int, mixed> $arguments
     * @param string $answered the call that answered, written as the spec writes it
     */
    public static function onAnswer(string $class, string $method, array $arguments, string $file, int $line, string $answered): self
    {
        return new self(sprintf(
            '%1$s::%2$s(%3$s) was called on what %6$s answered, at %4$s:%5$d. Stub %6$s.',
            ltrim($class, '\\'),
            $method,
            self::describeAll($arguments),
            ProjectRoot::here()->relative($file),
            $line,
            ltrim($answered, '\\'),
        ));
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private static function describeAll(array $arguments): string
    {
        $described = [];

        foreach ($arguments as $key => $argument) {
            $described[] = (is_string($key) ? '"' . $key . '" => ' : '') . self::describe($argument);
        }

        return implode(', ', $described);
    }

    private static function describe(mixed $argument): string
    {
        if ($argument instanceof ArgumentMatcher) {
            return $argument->describe();
        }

        if (is_object($argument)) {
            return ObjectName::of($argument);
        }

        if (is_array($argument)) {
            return '[' . self::describeAll($argument) . ']';
        }

        if (is_string($argument)) {
            return '"' . $argument . '"';
        }

        if ($argument === null) {
            return 'null';
        }

        return var_export($argument, true);
    }
}
