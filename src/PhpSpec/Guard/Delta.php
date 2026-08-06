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

namespace PhpSpec\Guard;

/**
 * @internal
 * What this session wrote: the lines it added or changed, by file.
 *
 * Line-level on purpose. Guard judges only what was touched, so a file full of
 * untested legacy code can be edited without the legacy being implicated, and
 * a refactor of covered code passes without a mode of its own. Deleted lines
 * are absent: removing code is never new logic left unspecified.
 */
final readonly class Delta
{
    /**
     * @param array<string, list<int>> $lines the changed line numbers, by project-relative path
     */
    private function __construct(private array $lines) {}

    /**
     * @param array<string, list<int>> $lines
     */
    public static function of(array $lines): self
    {
        $kept = [];
        foreach ($lines as $file => $numbers) {
            $numbers = array_values(array_unique($numbers));
            sort($numbers);
            if ($numbers !== []) {
                $kept[$file] = $numbers;
            }
        }

        ksort($kept);

        return new self($kept);
    }

    public static function nothing(): self
    {
        return new self([]);
    }

    /**
     * The files this session touched.
     *
     * @return list<string>
     */
    public function files(): array
    {
        return array_keys($this->lines);
    }

    /**
     * The lines it touched in one file, empty when it touched none.
     *
     * @return list<int>
     */
    public function lines(string $file): array
    {
        return $this->lines[$file] ?? [];
    }

    /**
     * Every changed line, by file.
     *
     * @return array<string, list<int>>
     */
    public function all(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
