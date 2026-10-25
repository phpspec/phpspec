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

namespace PhpSpec\Source;

use Closure;

/**
 * @internal
 * The lines of a source file left out of coverage: every interface, the
 * classes, traits, enums, methods and functions marked #[ExcludeFromCoverage]
 * or @codeCoverageIgnore, or named by the configuration, the lines a
 * @codeCoverageIgnore comment tags, and the lines that only declare. Read from
 * the tokens, so the file need not load.
 */
final readonly class ExcludedLines
{
    /**
     * @param list<int> $lines
     * @param bool $wholeFile whether nothing in the file is left to cover
     */
    public function __construct(
        private array $lines,
        private bool $wholeFile,
    ) {}

    /**
     * @param (Closure(string, string): bool)|null $named whether the configuration names a declaration, asked with its kind (class, method or function) and full name
     */
    public static function in(string $source, ?Closure $named = null): self
    {
        return (new ExclusionReader($source, $named ?? static fn(): bool => false))->read();
    }

    /**
     * @return list<int>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Whether the file has nothing left to cover: no function body outside
     * what is left out, interfaces included, and no code of its own.
     */
    public function wholeFile(): bool
    {
        return $this->wholeFile;
    }
}
