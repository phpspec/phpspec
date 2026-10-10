<?php

use PhpSpec\Filesystem;

/**
 * A project held in memory for the refactor command's specs: files are read
 * as the last write left them, so a later step sees what an earlier one
 * wrote, and a reverted step is seen gone.
 */
final class RefactorWorldFilesystem implements Filesystem
{
    /** @var array<string, string> absolute path => content */
    public array $files = [];

    /** @var array<string, int> absolute path => modification time */
    public array $times = [];

    public function exists(string $path): bool
    {
        return isset($this->files[$path]) || $this->isDir($path);
    }

    public function isFile(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function isDir(string $path): bool
    {
        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, rtrim($path, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    public function read(string $path): string
    {
        return $this->files[$path] ?? '';
    }

    public function readLines(string $path): array
    {
        return explode("\n", $this->read($path));
    }

    public function write(string $path, string $content): void
    {
        $this->files[$path] = $content;
        $this->times[$path] = max([0, ...array_values($this->times)]) + 1;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path], $this->times[$path]);
    }

    public function scandir(string $path): array
    {
        $entries = [];
        foreach (array_keys($this->files) as $file) {
            if (str_starts_with($file, rtrim($path, '/') . '/')) {
                $entries[explode('/', substr($file, strlen(rtrim($path, '/')) + 1))[0]] = true;
            }
        }

        return array_keys($entries);
    }

    public function mkdir(string $path): void {}

    public function requirePhp(string $path): mixed
    {
        return null;
    }

    public function mtime(string $path): int
    {
        return $this->times[$path] ?? 0;
    }
}
