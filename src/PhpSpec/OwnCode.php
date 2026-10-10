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

use Composer\Autoload\ClassLoader;

/**
 * @internal
 * Tells the project's own code from the libraries it depends on: a file is
 * the project's when it lies under the project and outside every vendor
 * directory a Composer autoloader serves.
 */
final readonly class OwnCode
{
    /**
     * @param list<ProjectRoot> $vendors
     */
    public function __construct(
        private ProjectRoot $project,
        private array $vendors,
    ) {}

    /**
     * The project the process runs in, and the vendor directories of the
     * Composer autoloaders it has registered.
     */
    public static function here(): self
    {
        return new self(ProjectRoot::here(), array_map(ProjectRoot::at(...), array_keys(ClassLoader::getRegisteredLoaders())));
    }

    public function holds(string $file): bool
    {
        if (!$this->project->holds($file)) {
            return false;
        }

        foreach ($this->vendors as $vendor) {
            if ($vendor->holds($file)) {
                return false;
            }
        }

        return true;
    }
}
