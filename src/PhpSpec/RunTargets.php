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

/**
 * @internal
 * What a run is pointed at, read from the targets it is given: each file or
 * directory it runs, and for a file it runs in part the lines and example
 * ids written after it, as "file:LINE" or "file@ID". Two spellings of a path
 * are one path, a leading "./" or not. A file given whole as well runs whole,
 * and a path inside a directory given whole is left to the directory.
 */
final readonly class RunTargets
{
    /**
     * @param array<string, array{lines: list<int>, ids: list<string>}> $paths each path as first given, to the lines and ids it is run by, none for one run whole
     * @param array<string, string> $files each target as given, to the file or directory it names
     */
    private function __construct(
        private array $paths,
        private array $files,
    ) {}

    /**
     * @param string $targets the targets, comma-separated
     */
    public static function of(string $targets): self
    {
        $spelled = [];
        $selected = [];
        $whole = [];
        $files = [];

        foreach (array_map('trim', explode(',', $targets)) as $target) {
            if ($target === '') {
                continue;
            }

            if (preg_match('/^(.+?)(?::(\d+)|@([0-9a-f]{12}))?$/', $target, $parts) !== 1) {
                continue;
            }

            $file = $parts[1];
            $key = self::key($file);
            $files[$target] = $file;
            $spelled[$key] ??= $file;
            $selected[$key] ??= ['lines' => [], 'ids' => []];

            if (($parts[3] ?? '') !== '') {
                $selected[$key]['ids'][] = $parts[3];
            } elseif (($parts[2] ?? '') !== '') {
                $selected[$key]['lines'][] = (int) $parts[2];
            } else {
                $whole[$key] = true;
            }
        }

        $paths = [];

        foreach ($spelled as $key => $path) {
            if (!self::insideOneOf($key, array_keys($whole))) {
                $paths[$path] = isset($whole[$key]) ? ['lines' => [], 'ids' => []] : $selected[$key];
            }
        }

        return new self($paths, $files);
    }

    /**
     * @return array<string, array{lines: list<int>, ids: list<string>}>
     */
    public function each(): array
    {
        return $this->paths;
    }

    /**
     * @return array<string, string>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * The targets that run of a path alone is given: its selectors, or the
     * path itself when it runs whole or was found inside a directory.
     *
     * @return list<string>
     */
    public function targetsFor(string $path): array
    {
        ['lines' => $lines, 'ids' => $ids] = $this->paths[$path] ?? ['lines' => [], 'ids' => []];
        $selectors = [...array_map(static fn(int $line): string => $path . ':' . $line, $lines), ...array_map(static fn(string $id): string => $path . '@' . $id, $ids)];

        return $selectors === [] ? [$path] : $selectors;
    }

    public function selectsWithin(): bool
    {
        foreach ($this->paths as ['lines' => $lines, 'ids' => $ids]) {
            if ($lines !== [] || $ids !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every target given selects within a file.
     */
    public function onlySelects(): bool
    {
        return $this->files !== [] && array_filter($this->files, static fn(string $file, string $target): bool => $file === $target, ARRAY_FILTER_USE_BOTH) === [];
    }

    /**
     * @param list<string> $directories
     */
    private static function insideOneOf(string $key, array $directories): bool
    {
        foreach ($directories as $directory) {
            if ($directory !== $key && ($directory === '' || str_starts_with($key . '/', $directory . '/'))) {
                return true;
            }
        }

        return false;
    }

    private static function key(string $path): string
    {
        $key = rtrim((string) preg_replace('~^(\./)+~', '', str_replace('\\', '/', $path)), '/');

        return $key === '.' ? '' : $key;
    }
}
