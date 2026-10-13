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

namespace PhpSpec\Coverage;

/**
 * @internal
 * What the PHP running phpspec has for coverage: which engines are loaded,
 * which can collect, and where PCOV looks.
 */
interface PhpRuntime
{
    /**
     * Whether the engine's extension is loaded, whatever its settings say.
     */
    public function loaded(CoverageAdapter $adapter): bool;

    /**
     * Whether the engine can collect in this process.
     */
    public function enabled(CoverageAdapter $adapter): bool;

    /**
     * The directory PCOV traces, as the project names it.
     */
    public function pcovDirectory(): string;

    /**
     * Whether the files under this directory are within what PCOV traces.
     */
    public function traces(string $directory): bool;

    /**
     * The value of an ini setting, or '' when it has none.
     */
    public function ini(string $name): string;
}
