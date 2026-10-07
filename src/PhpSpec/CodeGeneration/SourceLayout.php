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

/**
 * @internal
 * Where a class's source file lives. A class under a mapped namespace lives
 * in that namespace's directory with the prefix taken off its name, PSR-4
 * style, the longest matching prefix winning when mappings nest. A class
 * under no mapping lives below the source path with its whole name as
 * directories.
 */
final readonly class SourceLayout
{
    /** @var array<string, string> namespace prefix, no trailing backslash, to its directory */
    private array $mappings;

    private ?string $defaultNamespace;

    /**
     * @param string $srcPath where a class under no mapping goes, relative to the project root
     * @param array<string, string> $mappings namespace prefix to directory, relative to the project root
     * @param string|null $defaultNamespace what a name under no mapping is put into, when the config says so
     */
    public function __construct(private string $srcPath = 'src', array $mappings = [], ?string $defaultNamespace = null)
    {
        $normalised = [];

        foreach ($mappings as $prefix => $directory) {
            $prefix = trim($prefix, '\\');

            if ($prefix !== '') {
                $normalised[$prefix] = rtrim($directory, '/\\');
            }
        }

        $this->mappings = $normalised;
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

    public function isMapped(string $fqcn): bool
    {
        return $this->prefixOf($fqcn) !== null;
    }

    /**
     * The file for a class, relative to the project root.
     */
    public function relativeFileFor(string $fqcn): string
    {
        $fqcn = ltrim($fqcn, '\\');
        $prefix = $this->prefixOf($fqcn);

        if ($prefix === null) {
            return $this->fileUnder($this->srcPath, $fqcn);
        }

        return $this->fileUnder($this->mappings[$prefix], substr($fqcn, strlen($prefix) + 1));
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
     * Every file a class may load from, relative to the project root: the one
     * its mapping names, then the whole-name path under the source path.
     *
     * @return list<string>
     */
    public function candidateFilesFor(string $fqcn): array
    {
        $whole = $this->fileUnder($this->srcPath, ltrim($fqcn, '\\'));
        $mapped = $this->relativeFileFor($fqcn);

        return $mapped === $whole ? [$whole] : [$mapped, $whole];
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
        return $directory . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    }
}
