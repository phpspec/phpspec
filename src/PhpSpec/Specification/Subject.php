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

    /** @var array<string, \Closure(): mixed> let values not built yet, by property, each built when first read */
    public array $__phpspec_pending_lets = [];

    /**
     * A let value is built the first time an example reads it, so the hooks
     * have run by then and a let may read one declared after it. By
     * reference, so `$this->trace[] = 'x'` writes into the value. A name no
     * let owns comes into being as null, as a dynamic property written to
     * would have.
     */
    public function &__get(string $name): mixed
    {
        if (isset($this->__phpspec_pending_lets[$name])) {
            $build = $this->__phpspec_pending_lets[$name];
            unset($this->__phpspec_pending_lets[$name]);
            $this->$name = $build();
        } else {
            $this->$name = null;
        }

        return $this->$name;
    }

    public function __isset(string $name): bool
    {
        return isset($this->__phpspec_pending_lets[$name]);
    }

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
