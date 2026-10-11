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

use RuntimeException;

/**
 * @internal
 * Production filesystem implementation backed by PHP's native file functions.
 */
final class RealFilesystem implements Filesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    public function read(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$path}");
        }
        return $content;
    }

    public function readLines(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path);
        return $lines !== false ? $lines : [];
    }

    /**
     * @throws RuntimeException when the file, or the directory it goes in, cannot be written
     */
    public function write(string $path, string $content): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            $this->mkdir($dir);
        }

        if (!is_writable(file_exists($path) ? $path : $dir) || file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Cannot write %s: permission denied.', $path));
        }
    }

    public function delete(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function scandir(string $path): array
    {
        return scandir($path);
    }

    /**
     * @throws RuntimeException when the nearest directory above it cannot be written
     */
    public function mkdir(string $path): void
    {
        $existing = dirname($path);

        while (!is_dir($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }

        if (!is_writable($existing) || !mkdir(directory: $path, recursive: true)) {
            throw new RuntimeException(sprintf('Cannot create %s: permission denied.', $path));
        }
    }

    public function requirePhp(string $path): mixed
    {
        return require $path;
    }

    public function mtime(string $path): int
    {
        return file_exists($path) ? (filemtime($path) ?: 0) : 0;
    }
}
