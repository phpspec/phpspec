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
 * Data class representing a single Gherkin step with its keyword, text,
 * and optional DataTable or doc string attachment.
 */
readonly class StepNode
{
    /**
     * Creates a StepNode with its keyword, text, and optional table or doc string.
     *
     * @param string $keyword the step keyword (Given, When, Then, And, or But)
     * @param string $text the step text after the keyword
     * @param ?DataTable $table optional inline DataTable attached to this step
     * @param ?string $docString optional triple-quoted doc string attached to this step
     */
    public function __construct(
        public string $keyword,
        public string $text,
        public ?DataTable $table = null,
        public ?string $docString = null,
    ) {}
}
