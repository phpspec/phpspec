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

namespace PhpSpec\Coverage;

use Closure;
use PhpSpec\Source\CodeUnits;

/**
 * @internal
 * What several processes recorded of the same files, merged: each line at
 * the highest value any of them gave it, so a line one of them ran is
 * covered. Processes may compile a file differently, as PHP folds a class
 * constant into its value when it already has the class. A line no process
 * ran is not code when a process left it out of a function, method,
 * closure or hook it otherwise recorded, the line holding code of that unit
 * alone. A process that recorded nothing of the unit, as one that never
 * touched a property hook, says nothing about it, and neither does the
 * file's own code, which an engine records only in part when the file was
 * compiled before it started.
 */
final class Recordings
{
    /** @var list<array<string, array<int, int>>> what each process recorded, file => line => hit */
    private array $processes = [];

    /** @var Closure(string): ?string */
    private readonly Closure $source;

    /**
     * @param (Closure(string): ?string)|null $source the source of a file, or null when it cannot be read
     */
    public function __construct(?Closure $source = null)
    {
        $this->source = $source ?? static fn(string $file): ?string => is_file($file) ? (string) file_get_contents($file) : null;
    }

    /**
     * @param array<string, array<int, int>> $recorded what one process recorded, file => line => hit
     */
    public function add(array $recorded): void
    {
        $this->processes[] = $recorded;
    }

    /**
     * @return array<string, array<int, int>> file => line => hit, -2 for a line that is not code
     */
    public function merged(): array
    {
        $merged = [];

        foreach ($this->processes as $recorded) {
            foreach ($recorded as $file => $lines) {
                foreach ($lines as $line => $hit) {
                    $merged[$file][$line] = max($merged[$file][$line] ?? $hit, $hit);
                }
            }
        }

        foreach ($merged as $file => $lines) {
            foreach ($this->compiledAway($file, $lines) as $line) {
                $merged[$file][$line] = -2;
            }
        }

        return $merged;
    }

    /**
     * The lines of a file no process ran that a process compiled away.
     *
     * @param array<int, int> $lines the file's lines, merged
     * @return list<int>
     */
    private function compiledAway(string $file, array $lines): array
    {
        $recorders = array_values(array_filter(array_map(static fn(array $recorded): ?array => $recorded[$file] ?? null, $this->processes)));
        $unsure = array_keys(array_filter($lines, static function (int $hit, int $line) use ($recorders): bool {
            if ($hit !== -1) {
                return false;
            }

            foreach ($recorders as $recorded) {
                if (!isset($recorded[$line])) {
                    return true;
                }
            }

            return false;
        }, ARRAY_FILTER_USE_BOTH));

        $source = $unsure === [] ? null : ($this->source)($file);

        if ($source === null) {
            return [];
        }

        $units = new CodeUnits($source);
        $reached = array_map(static fn(array $recorded): array => self::unitsReached($units, $recorded), $recorders);

        return array_values(array_filter($unsure, static function (int $line) use ($units, $recorders, $reached): bool {
            $unit = $units->soleOwner($line);

            if ($unit === null || $unit === CodeUnits::FILE) {
                return false;
            }

            foreach ($recorders as $process => $recorded) {
                if (!isset($recorded[$line]) && isset($reached[$process][$unit])) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * The units a process recorded a line of, a line holding code of that
     * unit alone.
     *
     * @param array<int, int> $recorded
     * @return array<int, true>
     */
    private static function unitsReached(CodeUnits $units, array $recorded): array
    {
        $reached = [];

        foreach (array_keys($recorded) as $line) {
            $unit = $units->soleOwner($line);

            if ($unit !== null) {
                $reached[$unit] = true;
            }
        }

        return $reached;
    }
}
