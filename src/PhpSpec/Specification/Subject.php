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
 * @internal
 * Dynamic-property object serving as $this inside spec closures. Holds shared
 * state set by let() bindings; a fresh Subject is the world for each run.
 */
#[\AllowDynamicProperties]
final class Subject implements World
{
    /** @var array<string, object> mocks created by let() parameter injection */
    public array $__phpspec_let_mocks = [];

    /**
     * Loads a spec file, executing its top-level describe()/it() calls so they
     * register their blocks. The require runs in this method's scope, so every
     * closure defined at the file's top level is bound to $this — that binding
     * is what makes $this->foo work inside examples. Loading is done at most
     * once per file per process (see SpecFileCache); each run then rebinds the
     * parsed closures to its own fresh Subject rather than requiring again.
     *
     * @param string $path filesystem path to the .spec.php file
     * @return void
     */
    public function load(string $path): void
    {
        require $path;
    }
}
