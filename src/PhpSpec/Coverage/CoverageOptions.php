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
 * Value object describing which coverage reports were requested and where
 * they should be written, resolved from the run command CLI options.
 */
final readonly class CoverageOptions
{
    /**
     * @param string $srcPath the source directory to scope the reports to
     * @param bool $showText whether to render the text coverage report
     * @param string|null $cloverPath path for the Clover XML report, or null to skip
     * @param string|null $htmlPath directory for the HTML report, or null to skip
     * @param string|null $jsonPath path for the JSON coverage report, or null to skip
     * @param string|null $coverageMin minimum coverage percentage threshold, or null to skip
     * @param string|null $partialPath path to dump raw collector state to instead of rendering reports; used internally by parallel workers
     */
    public function __construct(
        public string $srcPath,
        public bool $showText = false,
        public ?string $cloverPath = null,
        public ?string $htmlPath = null,
        public ?string $jsonPath = null,
        public ?string $coverageMin = null,
        public ?string $partialPath = null,
    ) {}
}
