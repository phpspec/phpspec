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
 * Dynamic-property object serving as $this inside step closures.
 * Allows steps within a scenario to share state by setting and reading arbitrary properties.
 * A fresh instance is created for each scenario.
 */
#[\AllowDynamicProperties]
class StepWorld {}
