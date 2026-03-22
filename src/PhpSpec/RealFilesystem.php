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
 * Production filesystem implementation backed by PHP's native file functions.
 */
final class RealFilesystem implements Filesystem
{
    /** {@inheritdoc} */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /** {@inheritdoc} */
    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /** {@inheritdoc} */
    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    /** {@inheritdoc} */
    public function read(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }
        return $content;
    }

    /** {@inheritdoc} */
    public function readLines(string $path): array
    {
        $lines = file($path);
        return $lines !== false ? $lines : [];
    }

    /** {@inheritdoc} */
    public function write(string $path, string $content): void
    {
        if (!$this->exists(dirname($path))) {
            $this->mkdir(dirname($path));
        }
        file_put_contents($path, $content);
    }

    /** {@inheritdoc} */
    public function scandir(string $path): array
    {
        return scandir($path);
    }

    /** {@inheritdoc} */
    public function mkdir(string $path): void
    {
        mkdir(directory: $path, recursive: true);
    }

    /** {@inheritdoc} */
    public function requirePhp(string $path): mixed
    {
        return require $path;
    }
}
