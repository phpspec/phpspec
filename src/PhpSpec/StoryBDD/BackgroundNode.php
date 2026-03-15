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
 * Data class representing a Gherkin Background section containing steps that run before each scenario.
 */
readonly class BackgroundNode
{
    /**
     * Creates a BackgroundNode with the given steps.
     *
     * @param StepNode[] $steps ordered list of StepNode instances to execute before each scenario
     */
    public function __construct(/** @var StepNode[] */ public array $steps) {}
}
