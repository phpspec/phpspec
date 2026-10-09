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

namespace PhpSpec\Report;

use PhpSpec\ObjectName;

/**
 * @internal
 * A value as a failure shows it to a person: a string in quotes, a number
 * bare, a float at full precision, null as null, an array element by element,
 * an object by its name with its properties when the name alone tells nothing.
 * One rule for every formatter that puts a value on the screen, and for the
 * wire a parallel worker reports over, so a value reads the same whichever
 * process compared it.
 */
final class Typed
{
    /** How long a string gets before it is capped. */
    public const STRING_MAX = 60;

    /** How many elements of an array are shown before saying how many are left. */
    private const ARRAY_MAX = 10;

    /** How deep an object's properties are shown. */
    private const OBJECT_DEPTH = 3;

    /**
     * A value typed, so "42" and 42, null and "null", 0.3 and
     * 0.30000000000000004 read apart. A long string is capped to its first
     * thirty and last thirty characters around a [...] marker, because a
     * pair names the difference, not the whole blob.
     */
    public static function value(mixed $value, int $depth = 0): string
    {
        if (is_string($value)) {
            $capped = strlen($value) > self::STRING_MAX
                ? substr($value, 0, 30) . '[...]' . substr($value, -30)
                : $value;

            return '"' . self::escaped($capped) . '"';
        }

        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            is_float($value) => is_finite($value) ? var_export($value, true) : (string) $value,
            is_array($value) => self::listing($value, $depth),
            is_object($value) => self::object($value, $depth),
            default => (string) $value,
        };
    }

    public static function escaped(string $value): string
    {
        return str_replace(["\r\n", "\n", "\r"], '\n', $value);
    }

    /**
     * An object named by what it is, with its properties when the name alone
     * tells nothing: two points that differ read Point{x: 1, y: 2} against
     * Point{x: 1, y: 3}, not Point against Point. An enum, a throwable or
     * anything that describes itself is named as it describes itself. An
     * object compared in another process is shown as that process showed it.
     */
    private static function object(object $value, int $depth): string
    {
        if ($value instanceof ReportedObject) {
            return $value->shown;
        }

        $name = ObjectName::of($value);

        if ($name !== $value::class || $depth >= self::OBJECT_DEPTH) {
            return $name;
        }

        $properties = [];
        foreach (array_slice((array) $value, 0, self::ARRAY_MAX, true) as $key => $item) {
            $properties[] = preg_replace('/^\0.*\0/', '', (string) $key) . ': ' . self::value($item, $depth + 1);
        }

        return $properties === [] ? $name : $name . '{' . implode(', ', $properties) . '}';
    }

    /**
     * An array by the same rule as anything else in it, element by element. Not
     * var_export: an array holding an object with a reference back to itself
     * makes that emit a PHP warning, and a report about a failure must never
     * become a failure of its own.
     *
     * @param array<array-key, mixed> $value
     */
    private static function listing(array $value, int $depth): string
    {
        $shown = array_slice($value, 0, self::ARRAY_MAX, true);
        $parts = [];

        foreach ($shown as $key => $item) {
            $parts[] = is_int($key) ? self::value($item, $depth + 1) : $key . ' => ' . self::value($item, $depth + 1);
        }

        if (count($value) > self::ARRAY_MAX) {
            $parts[] = '… ' . (count($value) - self::ARRAY_MAX) . ' more';
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
