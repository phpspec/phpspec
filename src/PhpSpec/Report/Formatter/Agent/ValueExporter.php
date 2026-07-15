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

/**
 * @internal
 * Exports an arbitrary value into a compact, JSON-encodable shape for the agent
 * formatter: scalars pass through, long strings and large arrays are truncated
 * with a marker of their real length, and objects become "ClassName#id" — never
 * a full object graph — so a single expectation cannot flood the model's context.
 */
final class ValueExporter
{
    private const STRING_MAX = 200;
    private const ARRAY_MAX = 10;
    private const MAX_DEPTH = 5;

    /**
     * Exports a value to a JSON-encodable representation.
     *
     * @return mixed a scalar, a compact array, or a truncation marker array
     */
    public static function export(mixed $value, int $depth = 0): mixed
    {
        if (is_object($value)) {
            return $value::class . '#' . spl_object_id($value);
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
     * Returns the string as-is, or a truncation marker when it exceeds the cap.
     *
     * @return string|array{truncated: true, length: int, value: string}
     */
    private static function exportString(string $value): string|array
    {
        if (mb_strlen($value) <= self::STRING_MAX) {
            return $value;
        }

        return [
            'truncated' => true,
            'length' => mb_strlen($value),
            'value' => mb_substr($value, 0, self::STRING_MAX),
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
