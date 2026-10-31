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

use PhpSpec\CodeGeneration\SourceLayout;

/**
 * @internal
 * Loads the project's classes as its configuration lays them out: from the
 * "autoload" map first, then where the source layout puts a class. It names
 * the directories it loads from, for whatever must read the project's source.
 */
final readonly class ProjectAutoloader
{
    /**
     * @param array<string, string> $prefixes namespace prefix to the directory its classes are in
     * @param string $root the project root the layout's directories are under
     */
    public function __construct(
        private array $prefixes,
        private SourceLayout $layout,
        private string $root,
    ) {}

    public function __invoke(string $class): void
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $file = rtrim($baseDir, '/') . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

                if (file_exists($file)) {
                    require $file;

                    return;
                }
            }
        }

        foreach ($this->layout->candidateFilesFor($class) as $file) {
            if (file_exists($this->root . DIRECTORY_SEPARATOR . $file)) {
                require $this->root . DIRECTORY_SEPARATOR . $file;

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return [
            ...array_values($this->prefixes),
            ...array_map(fn(string $directory): string => $this->root . '/' . $directory, [$this->layout->srcPath(), ...array_values($this->layout->mappings())]),
        ];
    }
}
