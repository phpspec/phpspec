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
/** @internal */
final class Subject implements World
{
    /** @var array<string, object> mocks created by let() parameter injection */
    public array $__phpspec_let_mocks = [];
    /**
     * Loads the spec file, making describe()/context()/it() calls register their blocks.
     *
     * A plain `require` is used, not `require_once`: describe()/context()/it()
     * attach their blocks to whichever scope is currently pushed on the
     * dispatcher (see Specification::run()), so the file must re-execute on
     * every run — long-lived processes like `phpspec pair` run the same spec
     * file's path more than once per session, and `require_once` would only
     * ever fire the registration on the first run, silently dropping every
     * later one. A spec file that also declares a class/function/interface at
     * the top level (unusual for a spec file, but not disallowed) will fatal
     * with a "Cannot redeclare" error on a second run in the same process —
     * PHP does not allow catching that error, so it can't be worked around
     * here; avoid top-level declarations in spec files.
     *
     * @param string $path filesystem path to the .spec.php file
     */
    public function __construct(string $path)
    {
        require($path);
    }
}
