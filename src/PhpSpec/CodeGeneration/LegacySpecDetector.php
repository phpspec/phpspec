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

namespace PhpSpec\CodeGeneration;

/**
 * @internal
 * The single detector for phpspec 8 ObjectBehavior idioms in spec content,
 * shared by every guard that vets AI-written specs (the agent pipeline's
 * propose_edit and pair's raw spec writes). The model's training prior leans
 * hard on ObjectBehavior, so this is enforced in code, never just discouraged
 * in a prompt; keeping it in one place stops the guards drifting apart.
 */
final class LegacySpecDetector
{
    /**
     * Whether spec content uses phpspec-8 ObjectBehavior idioms rather than the
     * phpspec-9 functional DSL: an "ObjectBehavior" class, the old `spec\` file
     * namespace, `->shouldXxx()` matchers, or a method called directly on
     * `$this` (the subject). None of those are valid phpspec 9, where `$this`
     * only carries let-bound values (property access, never a subject call).
     */
    public static function looksLegacy(string $content): bool
    {
        return str_contains($content, 'ObjectBehavior')
            || str_contains($content, 'namespace spec\\')
            || preg_match('~->should[A-Z]~', $content) === 1
            || preg_match('~\$this->\w+\s*\(~', $content) === 1;
    }
}
