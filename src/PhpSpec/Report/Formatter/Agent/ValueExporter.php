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

namespace PhpSpec\Report\Formatter\Agent;

use PhpSpec\ObjectName;

/**
 * @internal
 * Exports an arbitrary value into a compact, JSON-encodable shape for the agent
 * formatter: scalars pass through, long strings and large arrays are truncated
 * with a marker of their real length, and objects are named rather than dumped,
 * so a single expectation cannot flood the model's context.
 */
final class ValueExporter
{
    private const STRING_MAX = 200;
    private const ARRAY_MAX = 10;
    private const MAX_DEPTH = 5;

    /** Printed output earns more room than a compared value: it is the diagnosis. */
    private const OUTPUT_MAX = 4000;

    /**
     * Exports a value to a JSON-encodable representation.
     *
     * @return mixed a scalar, a compact array, or a truncation marker array
     */
    public static function export(mixed $value, int $depth = 0): mixed
    {
        if (is_object($value)) {
            return ObjectName::of($value);
        }

        if (is_string($value)) {
            return self::exportString($value);
        }

        if (is_array($value)) {
            return self::exportArray($value, $depth);
        }

        return $value;
    }

    /**
     * Exports what a subject printed, under a cap of its own: a compared value
     * is a value, but printed output is the diagnosis and is worth more room.
     *
     * @return string|array{truncated: true, length: int, value: string}
     */
    public static function exportOutput(string $value): string|array
    {
        return self::exportString($value, self::OUTPUT_MAX);
    }

    /**
     * Returns the string as-is, or a truncation marker when it exceeds the cap.
     *
     * @return string|array{truncated: true, length: int, value: string}
     */
    private static function exportString(string $value, int $max = self::STRING_MAX): string|array
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return [
            'truncated' => true,
            'length' => mb_strlen($value),
            'value' => mb_substr($value, 0, $max),
        ];
    }

    /**
     * Exports each element (preserving keys), truncating to the first ARRAY_MAX
     * elements when the array is larger and marking the real length. Depth is
     * bounded so a deeply nested structure cannot recurse without limit.
     *
     * @param array<array-key, mixed> $value
     * @return mixed
     */
    private static function exportArray(array $value, int $depth): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['truncated' => true, 'length' => count($value)];
        }

        $count = count($value);
        $slice = $count > self::ARRAY_MAX ? array_slice($value, 0, self::ARRAY_MAX, true) : $value;

        $exported = [];
        foreach ($slice as $key => $item) {
            $exported[$key] = self::export($item, $depth + 1);
        }

        if ($count > self::ARRAY_MAX) {
            return ['truncated' => true, 'length' => $count, 'value' => $exported];
        }

        return $exported;
    }
}
