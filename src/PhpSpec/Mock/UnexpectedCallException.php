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
use PhpSpec\ProjectRoot;

/**
 * A call on a double, made from outside the spec code, that no allow() or
 * expect() declared.
 */
final class UnexpectedCallException extends LogicException
{
    /**
     * @param array<int, mixed> $arguments
     */
    public static function to(string $class, string $method, array $arguments, string $file, int $line): self
    {
        return new self(sprintf(
            '%s::%s(%s) was called but not expected, at %s:%d. Declare the call with allow() or expect(), or make the double a dummy() if its calls do not matter.',
            ltrim($class, '\\'),
            $method,
            self::describeAll($arguments),
            ProjectRoot::here()->relative($file),
            $line,
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
            return get_debug_type($argument);
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
