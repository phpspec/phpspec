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
 * Loads the project's classes from where the source layout puts them. It
 * names the directories it loads from, for whatever must read the project's
 * source.
 */
final readonly class ProjectAutoloader
{
    /**
     * @param string $root the project root the layout's directories are under
     */
    public function __construct(
        private SourceLayout $layout,
        private string $root,
    ) {}

    public function __invoke(string $class): void
    {
        $file = $this->root . DIRECTORY_SEPARATOR . $this->layout->relativeFileFor($class);

        if (file_exists($file)) {
            require $file;
        }
    }

    /**
     * @return list<string>
     */
    public function directories(): array
    {
        return array_map(fn(string $directory): string => $this->root . '/' . $directory, $this->layout->directories());
    }
}
