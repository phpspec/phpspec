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

namespace PhpSpec\Ai;

use PhpSpec\Filesystem;

final class AiTools
{
    /**
     * Create a tool that reads the contents of a project file.
     *
     * @param Filesystem $filesystem Filesystem abstraction for file operations
     *
     * @return Tool AI tool that accepts a file path and returns its contents
     */
    public static function readFile(Filesystem $filesystem): Tool
    {
        return Tool::make(
            name: 'read_file',
            description: 'Read the contents of a file from the project.',
            parameters: [
                'path' => [
                    'type' => 'string',
                    'description' => 'Absolute or relative path to a file',
                ],
            ],
            handler: function (array $args) use ($filesystem) {
                $path = $args['path'];
                if (!str_starts_with($path, '/')) {
                    $path = getcwd() . '/' . $path;
                }

                if (!$filesystem->exists($path)) {
                    return "File not found: {$args['path']}";
                }

                return $filesystem->read($path);
            },
        );
    }

    /**
     * Create a tool that lists files in a project directory.
     *
     * @param Filesystem $filesystem Filesystem abstraction for file operations
     *
     * @return Tool AI tool that accepts a directory path and returns a newline-separated file listing
     */
    public static function listFiles(Filesystem $filesystem): Tool
    {
        return Tool::make(
            name: 'list_files',
            description: 'List files in a project directory.',
            parameters: [
                'directory' => [
                    'type' => 'string',
                    'description' => 'Relative directory path (e.g. "spec/App", "src/App")',
                    'default' => '.',
                ],
            ],
            handler: function (array $args) use ($filesystem) {
                $dir = getcwd() . '/' . ltrim($args['directory'] ?? '.', '/');

                if (!$filesystem->exists($dir) || !$filesystem->isDir($dir)) {
                    return "Directory not found: {$args['directory']}";
                }

                $entries = $filesystem->scandir($dir);
                $files = array_filter($entries, fn($e) => $e !== '.' && $e !== '..');
                sort($files);

                return implode("\n", $files);
            },
        );
    }
}
