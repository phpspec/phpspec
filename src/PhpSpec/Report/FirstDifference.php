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

use PhpSpec\Report\Formatter\Agent\ValueExporter;

/**
 * @internal
 * Where two values of the same kind part: the byte offset of two strings, with
 * a window of each from there, or the path into two arrays of the same shape.
 * Saves a reader diffing two long values by eye to find a trailing newline.
 */
final class FirstDifference
{
    private const WINDOW = 40;

    /**
     * @return array{offset: int, expected: string, actual: string}|array{path: string, expected: mixed, actual: mixed}|null
     */
    public static function between(mixed $expected, mixed $actual): ?array
    {
        if (is_string($expected) && is_string($actual) && $expected !== $actual) {
            return self::betweenStrings($expected, $actual);
        }

        if (is_array($expected) && is_array($actual) && $expected !== $actual) {
            return self::betweenArrays($expected, $actual, '');
        }

        return null;
    }

    /**
     * @return array{offset: int, expected: string, actual: string}
     */
    private static function betweenStrings(string $expected, string $actual): array
    {
        $shorter = min(strlen($expected), strlen($actual));
        $offset = 0;

        while ($offset < $shorter && $expected[$offset] === $actual[$offset]) {
            $offset++;
        }

        return [
            'offset' => $offset,
            'expected' => substr($expected, $offset, self::WINDOW),
            'actual' => substr($actual, $offset, self::WINDOW),
        ];
    }

    /**
     * Two arrays of one shape part at the first key whose values differ; two of
     * different shapes differ as wholes, which at the top is nothing to point
     * at and deeper down is the key that holds them.
     *
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     * @return array{path: string, expected: mixed, actual: mixed}|null
     */
    private static function betweenArrays(array $expected, array $actual, string $path): ?array
    {
        if (array_keys($expected) !== array_keys($actual)) {
            return $path === '' ? null : ['path' => $path, 'expected' => ValueExporter::export($expected), 'actual' => ValueExporter::export($actual)];
        }

        foreach ($expected as $key => $value) {
            if ($value === $actual[$key]) {
                continue;
            }

            $at = is_int($key) ? sprintf('%s[%d]', $path, $key) : ($path === '' ? (string) $key : $path . '.' . $key);

            if (is_array($value) && is_array($actual[$key])) {
                return self::betweenArrays($value, $actual[$key], $at);
            }

            return ['path' => $at, 'expected' => ValueExporter::export($value), 'actual' => ValueExporter::export($actual[$key])];
        }

        return null;
    }
}
