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

namespace PhpSpec;

use Stringable;
use Throwable;
use UnitEnum;

/**
 * @internal
 * How an object appears in a failure: by what it is, never by which instance it
 * happens to be. An object id changes between runs, so a report that carries one
 * cannot be compared with the same report from the run before it, and it tells
 * the reader nothing they can act on either.
 *
 * One statement of the rule, for every place a value is shown: the failure
 * message, the array it sits in, and the agent document.
 */
final class ObjectName
{
    private const STRING_MAX = 60;

    /**
     * The name to show for an object: an enum by its case, anything that can
     * describe itself by that description, and everything else by its class.
     */
    public static function of(object $value): string
    {
        if ($value instanceof UnitEnum) {
            return $value::class . '::' . $value->name;
        }

        // A throwable describes itself with its whole stack trace, which is a
        // wall of absolute paths that shift with every edit. Its message is the
        // part that identifies it.
        if ($value instanceof Throwable) {
            return self::named($value::class, $value->getMessage());
        }

        if ($value instanceof Stringable) {
            return self::named($value::class, self::describe($value));
        }

        return $value::class;
    }

    /**
     * A class and the description that tells one of its instances from another,
     * as one rule: the class alone when there is nothing to add, so an exception
     * carrying no message reads as "RuntimeException" and not as
     * "RuntimeException("")". Also serves the side of a comparison that has no
     * object yet, only the class and message a matcher was asked for.
     */
    public static function named(string $class, ?string $description): string
    {
        $description = $description !== null ? self::clip($description) : '';

        return $description === '' ? $class : $class . '("' . $description . '")';
    }

    /**
     * The object's own description, or an empty one when asking for it fails:
     * a report about a failure must not become a failure of its own.
     */
    private static function describe(Stringable $value): string
    {
        try {
            return (string) $value;
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * The description as a name can carry it: one line, and short enough to sit
     * inside a message.
     */
    private static function clip(string $description): string
    {
        $line = strtok(trim($description), "\n");
        $excerpt = $line === false ? '' : $line;

        return mb_strlen($excerpt) > self::STRING_MAX
            ? mb_substr($excerpt, 0, self::STRING_MAX - 1) . '…'
            : $excerpt;
    }
}
