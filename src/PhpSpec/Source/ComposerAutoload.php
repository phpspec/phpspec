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

use PhpSpec\Filesystem;

/**
 * @internal
 * The PSR-4 autoloading a project's composer.json declares: each namespace
 * prefix with the directories its classes are in, relative to the project
 * root, in the order composer.json lists them.
 */
final readonly class ComposerAutoload
{
    /**
     * @param array<string, list<string>> $psr4 namespace prefix to its directories
     */
    public function __construct(private array $psr4 = []) {}

    /**
     * What the composer.json under the root declares, or null when there is
     * no composer.json.
     */
    public static function of(string $rootDir, Filesystem $fs): ?self
    {
        $path = rtrim($rootDir, '/') . '/composer.json';

        if (!$fs->exists($path)) {
            return null;
        }

        return self::fromJson($fs->read($path));
    }

    /**
     * What a composer.json's content declares.
     */
    public static function fromJson(string $json): self
    {
        $composer = json_decode($json, true);
        $declared = is_array($composer) ? ($composer['autoload']['psr-4'] ?? null) : null;
        $psr4 = [];

        foreach (is_array($declared) ? $declared : [] as $prefix => $directories) {
            $directories = array_values(array_filter((array) $directories, static fn(mixed $directory): bool => is_string($directory) && $directory !== ''));

            if (is_string($prefix) && $directories !== []) {
                $psr4[$prefix] = $directories;
            }
        }

        return new self($psr4);
    }

    /**
     * Each prefix to the first directory composer.json lists for it, where
     * Composer looks first and a new class goes.
     *
     * @return array<string, string>
     */
    public function mappings(): array
    {
        return array_map(static fn(array $directories): string => self::relative($directories[0]), $this->psr4);
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return array_values(array_unique(array_map(self::relative(...), array_merge([], ...array_values($this->psr4)))));
    }

    public function mapsTo(string $directory): bool
    {
        return in_array(self::relative($directory), $this->directories(), true);
    }

    /**
     * Whether a mapping places the namespace: one of its prefixes, or "" for
     * every namespace.
     */
    public function places(string $namespace): bool
    {
        $namespace = trim($namespace, '\\') . '\\';

        foreach (array_keys($this->psr4) as $prefix) {
            if ($prefix === '' || str_starts_with($namespace, rtrim($prefix, '\\') . '\\')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The namespace mapped into the directory anywhere but at its whole name
     * there, said as Composer's claim on it, or null when every mapping into
     * it agrees with classes filed below it by their whole names.
     */
    public function claimOn(string $directory): ?string
    {
        $directory = self::relative($directory);

        foreach ($this->psr4 as $prefix => $directories) {
            $wholeName = ltrim($directory . '/' . str_replace('\\', '/', rtrim($prefix, '\\')), '/');

            foreach (array_map(self::relative(...), $directories) as $mapped) {
                $inside = $mapped === $directory || str_starts_with($mapped, $directory . '/');

                if ($prefix !== '' && $inside && $mapped !== $wholeName) {
                    return sprintf('Composer maps %s/ to %s', $mapped, $prefix);
                }
            }
        }

        return null;
    }

    private static function relative(string $directory): string
    {
        return rtrim((string) preg_replace('~^(\./)+~', '', str_replace('\\', '/', $directory)), '/');
    }
}
