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

namespace PhpSpec\Report;

/**
 * @internal
 * An object that was compared in another process. What is left of it here is
 * the name that process gave it and the way that process showed it, and a
 * formatter reads those as it would have read the object itself.
 */
final readonly class ReportedObject
{
    /**
     * @param string $name how the object is named, as ObjectName names one
     * @param string $shown how the object is shown, as Typed shows one
     */
    public function __construct(
        public string $name,
        public string $shown,
    ) {}
}
