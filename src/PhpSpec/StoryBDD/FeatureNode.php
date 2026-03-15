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
 * Data class representing a parsed Gherkin Feature with its title, description,
 * optional background, scenarios, and tags.
 */
readonly class FeatureNode
{
    /**
     * Creates a FeatureNode with its title, description, background, scenarios, and tags.
     *
     * @param string $title the feature title from the "Feature:" line
     * @param string $description free-text description below the Feature line
     * @param ?BackgroundNode $background optional background steps run before each scenario
     * @param ScenarioNode[] $scenarios list of ScenarioNode or ScenarioOutlineNode instances
     * @param string[] $tags tags applied to the feature (e.g. @wip, @slow)
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?BackgroundNode $background,
        /** @var ScenarioNode[] */
        public array $scenarios,
        /** @var string[] */
        public array $tags = [],
    ) {}
}
