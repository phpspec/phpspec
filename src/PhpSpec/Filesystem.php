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
 * Abstraction over filesystem operations for testability via mock injection.
 */
interface Filesystem
{
    /**
     * Checks whether a path exists.
     *
     * @param string $path filesystem path
     */
    public function exists(string $path): bool;

    /**
     * Checks whether the path is a regular file.
     *
     * @param string $path filesystem path
     */
    public function isFile(string $path): bool;

    /**
     * Checks whether the path is a directory.
     *
     * @param string $path filesystem path
     */
    public function isDir(string $path): bool;

    /**
     * Reads the entire contents of a file as a string.
     *
     * @param string $path file to read
     */
    public function read(string $path): string;

    /**
     * Reads a file into an array of lines.
     *
     * @param string $path file to read
     * @return array<string>
     */
    public function readLines(string $path): array;

    /**
     * Writes content to a file, creating parent directories as needed.
     *
     * @param string $path destination file path
     * @param string $content data to write
     */
    public function write(string $path, string $content): void;

    /**
     * Lists entries in a directory.
     *
     * @param string $path directory to scan
     * @return array<string> directory entries including '.' and '..'
     */
    public function scandir(string $path): array;

    /**
     * Creates a directory recursively.
     *
     * @param string $path directory path to create
     */
    public function mkdir(string $path): void;

    /**
     * Includes a PHP file and returns its return value.
     *
     * @param string $path PHP file to require
     */
    public function requirePhp(string $path): mixed;

    /**
     * Returns a file's last-modified time as a Unix timestamp, or 0 when it
     * cannot be determined.
     *
     * @param string $path file to inspect
     */
    public function mtime(string $path): int;
}
