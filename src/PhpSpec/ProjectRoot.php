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
 * A directory, and every spelling of it a path might arrive in.
 *
 * PhpSpec names files relative to the project all over: coverage, the agent
 * stream, guard's delta. Each of those compares a path PHP produced against a
 * root PhpSpec asked for, and the two need not be spelled alike. Separators
 * differ on Windows, case is not significant there, and the same directory
 * answers to both a short name ("RUNNER~1") and a long one ("runneradmin"),
 * depending on which call produced it.
 *
 * A root that recognises only one spelling strips nothing, and a path that
 * stays absolute is not merely ugly: it is a file the coverage report cannot
 * name, and a file guard cannot find in its own coverage, which it then reads
 * as logic no example reached.
 */
final readonly class ProjectRoot
{
    /**
     * @param list<string> $spellings every form of this directory, with forward
     *                                slashes and a trailing one
     */
    private function __construct(private array $spellings) {}

    public static function at(string $directory): self
    {
        $spellings = [];

        foreach ([$directory, realpath($directory)] as $spelling) {
            if (is_string($spelling) && $spelling !== '') {
                $spellings[] = rtrim(str_replace('\\', '/', $spelling), '/') . '/';
            }
        }

        return new self(array_values(array_unique($spellings)));
    }

    /**
     * The directory the process is working in.
     */
    public static function here(): self
    {
        return self::at(getcwd() ?: '.');
    }

    /**
     * A path as the project refers to it, or unchanged when it lies outside.
     */
    public function relative(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        foreach ($this->spellings as $spelling) {
            if ($this->under($path, $spelling)) {
                return substr($path, strlen($spelling));
            }
        }

        return $path;
    }

    /**
     * Whether this path lies inside the directory.
     */
    public function holds(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        foreach ($this->spellings as $spelling) {
            if ($this->under($path, $spelling)) {
                return true;
            }
        }

        return false;
    }

    private function under(string $path, string $spelling): bool
    {
        if (str_starts_with($path, $spelling)) {
            return true;
        }

        // Windows does not distinguish paths by case, and the two calls that
        // produced these two strings did not have to agree about it.
        return DIRECTORY_SEPARATOR === '\\' && stripos($path, $spelling) === 0;
    }
}
