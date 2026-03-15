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

namespace PhpSpec\Specification;

/**
 * Dynamic-property object serving as $this inside spec closures. Loads the spec file
 * on construction and holds shared state set by let() bindings.
 */
#[\AllowDynamicProperties]
final class Subject implements World
{
    /**
     * Loads the spec file, making describe()/context()/it() calls register their blocks.
     *
     * @param string $path filesystem path to the .spec.php file
     */
    public function __construct(string $path)
    {
        require_once($path);
    }
}
