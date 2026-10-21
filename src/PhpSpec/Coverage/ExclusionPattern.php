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

/**
 * @internal
 * One entry of the coverage_exclude list, read by its shape: a path when it
 * holds a slash or ends in .php (a directory, a file, or a glob where *
 * stays within a directory and ** crosses them), a method when it holds ::,
 * a function when it ends in (), and otherwise a class, trait or enum. Names
 * match whatever their case, as PHP's do, * standing for any characters.
 */
final readonly class ExclusionPattern
{
    private string $entry;

    public function __construct(string $entry)
    {
        $this->entry = trim($entry);
    }

    /**
     * Whether a file, named from the project root, is under this path.
     */
    public function matchesPath(string $relative): bool
    {
        if (!$this->isPath()) {
            return false;
        }

        $pattern = self::plain($this->entry);
        $path = self::plain($relative);

        if (strpbrk($pattern, '*?') !== false) {
            return preg_match(self::glob($pattern), $path) === 1;
        }

        $directory = rtrim($pattern, '/');

        return $path === $directory || str_starts_with($path, $directory . '/');
    }

    /**
     * Whether this names the declaration: a class, trait or enum (kind
     * "class") by its full name, a method as Class::method, a function by its
     * full name.
     */
    public function matchesName(string $kind, string $name): bool
    {
        if ($this->isPath()) {
            return false;
        }

        $entry = ltrim($this->entry, '\\');
        $names = match (true) {
            str_ends_with($entry, '()') => 'function',
            str_contains($entry, '::') => 'method',
            default => 'class',
        };

        if ($names !== $kind) {
            return false;
        }

        $pattern = $names === 'function' ? substr($entry, 0, -2) : $entry;
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';

        return preg_match($regex, ltrim($name, '\\')) === 1;
    }

    private function isPath(): bool
    {
        return str_contains($this->entry, '/') || str_ends_with(strtolower($this->entry), '.php');
    }

    private static function plain(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, './') ? substr($path, 2) : $path;
    }

    private static function glob(string $pattern): string
    {
        $regex = strtr(preg_quote($pattern, '#'), [
            '\*\*/' => '(?:.*/)?',
            '\*\*' => '.*',
            '\*' => '[^/]*',
            '\?' => '[^/]',
        ]);

        return '#^' . $regex . '$#';
    }
}
