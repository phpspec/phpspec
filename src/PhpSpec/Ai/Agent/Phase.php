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

namespace PhpSpec\Ai\Agent;

/**
 * @internal
 * Where we are in the outside-in TDD cycle. The cycle's STRUCTURE is phpspec's
 * spec'd opinion and lives here, in code; its VOICE is the per-phase instruction
 * file (`Ai/Prompts/instructions/<value>.txt`), which stays editable text.
 */
enum Phase: string
{
    case WriteFeature = 'write-feature';
    case WriteSteps = 'write-steps';
    case WriteSpec = 'write-spec';
    case WriteCode = 'write-code';
    case Refactor = 'refactor';
}
