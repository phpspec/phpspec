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

namespace PhpSpec\CodeGeneration;

use PhpSpec\ProjectRoot;

/**
 * @internal
 * Where a class's source file lives. A class under a mapped namespace lives
 * in that namespace's directory with the prefix taken off its name, PSR-4
 * style, the longest matching prefix winning when mappings nest. A class
 * under no mapping lives with its whole name as directories below the
 * directory a mapping of "" names, Composer's fallback, else below the
 * source path.
 */
final readonly class SourceLayout
{
    /** @var array<string, string> namespace prefix, no trailing backslash, to its directory */
    private array $mappings;

    private ?string $defaultNamespace;

    private ?string $fallback;

    /**
     * @param string $srcPath where a class under no mapping goes, relative to the project root
     * @param array<string, string> $mappings namespace prefix to directory, relative to the project root, "" for the fallback
     * @param string|null $defaultNamespace what a name under no mapping is put into, when the config says so
     */
    public function __construct(private string $srcPath = 'src', array $mappings = [], ?string $defaultNamespace = null)
    {
        $normalised = [];
        $fallback = null;

        foreach ($mappings as $prefix => $directory) {
            $prefix = trim($prefix, '\\');

            if ($prefix === '') {
                $fallback = rtrim($directory, '/\\');
            } else {
                $normalised[$prefix] = rtrim($directory, '/\\');
            }
        }

        $this->mappings = $normalised;
        $this->fallback = $fallback;
        $this->defaultNamespace = $defaultNamespace === null ? null : trim($defaultNamespace, '\\');
    }

    /**
     * The layout one source path and one PSR-4 prefix used to describe.
     */
    public static function under(string $srcPath, string $prefix = ''): self
    {
        return new self($srcPath, $prefix === '' ? [] : [$prefix => $srcPath]);
    }

    public function srcPath(): string
    {
        return $this->srcPath;
    }

    /**
     * @return array<string, string>
     */
    public function mappings(): array
    {
        return $this->mappings;
    }

    public function defaultNamespace(): ?string
    {
        return $this->defaultNamespace;
    }

    /**
     * Every directory the project's own classes are in: the source path, each
     * mapped one, and the fallback.
     *
     * @return list<string>
     */
    public function directories(): array
    {
        return array_values(array_unique([$this->srcPath, ...array_values($this->mappings), ...($this->fallback === null ? [] : [$this->fallback])]));
    }

    /**
     * Its directories as a walk through the source takes them: once each,
     * none inside another.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        $directories = $this->directories();

        return array_values(array_filter($directories, static function (string $directory) use ($directories): bool {
            foreach ($directories as $other) {
                if ($other !== $directory && str_starts_with($directory . '/', rtrim($other, '/') . '/')) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Whether a file is the project's own source: in one of its directories.
     */
    public function holds(string $file): bool
    {
        foreach ($this->directories() as $directory) {
            if (ProjectRoot::at($directory)->holds($file)) {
                return true;
            }
        }

        return false;
    }

    public function isMapped(string $fqcn): bool
    {
        return $this->prefixOf($fqcn) !== null;
    }

    /**
     * Whether a mapping places the class: its namespace's, or the fallback.
     */
    public function covers(string $fqcn): bool
    {
        return $this->fallback !== null || $this->isMapped($fqcn);
    }

    /**
     * The file for a class, relative to the project root.
     */
    public function relativeFileFor(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');
        $prefix = $this->prefixOf($fqcn);

        if ($prefix === null) {
            return $this->fileUnder($this->wholeNamesBelow(), $fqcn);
        }

        return $this->fileUnder($this->mappings[$prefix], substr($fqcn, strlen($prefix) + 1));
    }

    /**
     * The class a file holds, by where it lies relative to the project root:
     * under the longest mapped directory, that mapping's prefix and the rest
     * of its path; elsewhere, its path as the whole name, below the directory
     * classes under no mapping go to.
     */
    public function classAt(string $relativeFile): string
    {
        $path = (string) preg_replace('~\.php$~', '', str_replace('\\', '/', $relativeFile));
        $owner = null;

        foreach ($this->mappings as $prefix => $directory) {
            $directory = str_replace('\\', '/', $directory) . '/';

            if (str_starts_with($path, $directory) && ($owner === null || strlen($directory) > strlen($owner[1]))) {
                $owner = [$prefix, $directory];
            }
        }

        if ($owner !== null) {
            return $owner[0] . '\\' . str_replace('/', '\\', substr($path, strlen($owner[1])));
        }

        $below = rtrim(str_replace('\\', '/', $this->wholeNamesBelow()), '/') . '/';

        return str_replace('/', '\\', str_starts_with($path, $below) ? substr($path, strlen($below)) : $path);
    }

    /**
     * The file for a class, as the generators write it.
     */
    public function filePathFor(string $fqcn): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . $this->relativeFileFor($fqcn);
    }

    /**
     * A class taken apart for writing: its short name, its namespace
     * declaration ready to follow the opening tag, and its file.
     *
     * @return array{shortName: string, namespace: string, filePath: string}
     */
    public function locate(string $fqcn): array
    {
        $pieces = explode('\\', ltrim($fqcn, '\\'));
        $shortName = array_pop($pieces);

        return [
            'shortName' => $shortName,
            'namespace' => $pieces === [] ? '' : "\n\nnamespace " . implode('\\', $pieces) . ';',
            'filePath' => $this->filePathFor($fqcn),
        ];
    }

    /**
     * What a name under no mapping would be under each mapped namespace.
     *
     * @return list<string>
     */
    public function candidatesFor(string $fqcn): array
    {
        $fqcn = ltrim($fqcn, '\\');

        return array_map(static fn(string $prefix): string => $prefix . '\\' . $fqcn, array_keys($this->mappings));
    }

    /**
     * A name under no mapping put into the default namespace; a mapped name,
     * or any name when there is no default, as it was.
     */
    public function withinDefaultNamespace(string $fqcn): string
    {
        if ($this->defaultNamespace === null || $this->isMapped($fqcn)) {
            return $fqcn;
        }

        return $this->defaultNamespace . '\\' . ltrim($fqcn, '\\');
    }

    private function wholeNamesBelow(): string
    {
        return $this->fallback ?? $this->srcPath;
    }

    private function prefixOf(string $fqcn): ?string
    {
        $fqcn = ltrim($fqcn, '\\');
        $longest = null;

        foreach (array_keys($this->mappings) as $prefix) {
            if (str_starts_with($fqcn, $prefix . '\\') && ($longest === null || strlen($prefix) > strlen($longest))) {
                $longest = $prefix;
            }
        }

        return $longest;
    }

    private function fileUnder(string $directory, string $relativeClass): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory . '/' . $relativeClass) . '.php';
    }
}
