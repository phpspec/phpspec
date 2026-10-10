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

namespace PhpSpec\StoryBDD;

/**
 * @internal
 * A path that may hold step definitions: `steps.php`, the default file, or
 * any `*.steps.php`.
 */
final readonly class StepsFile
{
    public const DEFAULT_NAME = 'steps.php';

    public const SUFFIX = '.steps.php';

    public function __construct(private string $path) {}

    public function isStepDefinitions(): bool
    {
        $name = basename(str_replace('\\', '/', $this->path));

        return $name === self::DEFAULT_NAME || str_ends_with($name, self::SUFFIX);
    }
}
