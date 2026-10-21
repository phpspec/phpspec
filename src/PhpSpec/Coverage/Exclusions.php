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

use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\ProjectRoot;
use PhpSpec\Source\ExcludedLines;

/**
 * @internal
 * What coverage leaves out of a project: the files the configuration names
 * by path, every interface, and the declarations and lines marked or named.
 * Each file is read once, however often it is asked about.
 */
final class Exclusions
{
    /** @var list<ExclusionPattern> */
    private readonly array $patterns;

    /** @var array<string, ExcludedLines> what each file leaves out, by its path from the project root */
    private array $read = [];

    /**
     * @param list<string> $entries the coverage_exclude list
     */
    public function __construct(
        array $entries,
        private readonly Filesystem $filesystem,
        private readonly string $baseDir,
    ) {
        $this->patterns = array_map(static fn(string $entry): ExclusionPattern => new ExclusionPattern($entry), $entries);
    }

    public static function of(Configuration $config, Filesystem $filesystem, string $baseDir = '.'): self
    {
        return new self($config->coverageExclusions(), $filesystem, $baseDir);
    }

    /**
     * The coverage data without what is left out: a file excluded whole, or
     * left with no lines, goes; otherwise its excluded lines do.
     *
     * @template T
     * @param array<string, array<int, T>> $data line values by line, by absolute path
     * @return array<string, array<int, T>>
     */
    public function appliedTo(array $data): array
    {
        $kept = [];

        foreach ($data as $file => $lines) {
            $relative = $this->relative($file);

            if ($this->pathExcluded($relative) || $this->linesOf($relative)->wholeFile()) {
                continue;
            }

            $left = array_diff_key($lines, array_flip($this->linesOf($relative)->lines()));

            if ($left !== []) {
                $kept[$file] = $left;
            }
        }

        return $kept;
    }

    /**
     * Whether this line is left out, the file named in full or from the
     * project root.
     */
    public function excludes(string $file, int $line): bool
    {
        $relative = $this->relative($file);

        if ($this->pathExcluded($relative)) {
            return true;
        }

        $excluded = $this->linesOf($relative);

        return $excluded->wholeFile() || in_array($line, $excluded->lines(), true);
    }

    private function pathExcluded(string $relative): bool
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->matchesPath($relative)) {
                return true;
            }
        }

        return false;
    }

    private function linesOf(string $relative): ExcludedLines
    {
        if (!isset($this->read[$relative])) {
            $path = rtrim($this->baseDir, '/\\') . '/' . $relative;
            $source = $this->filesystem->exists($path) ? $this->filesystem->read($path) : '';
            $this->read[$relative] = ExcludedLines::in($source, $this->named(...));
        }

        return $this->read[$relative];
    }

    private function named(string $kind, string $name): bool
    {
        foreach ($this->patterns as $pattern) {
            if ($pattern->matchesName($kind, $name)) {
                return true;
            }
        }

        return false;
    }

    private function relative(string $file): string
    {
        return ltrim(ProjectRoot::at($this->baseDir)->relative($file), '/');
    }
}
