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

use PhpSpec\Console\Command\Run\SuiteSummary;

/**
 * @internal
 * The project truth every AI command grounds itself in: the suite state, the
 * last-touched files, the file tree, and the contents of any files the
 * instruction names. A plain value, so evals script one instead of mocking an
 * accumulator; commands build only the sections their manifest asks for.
 */
final readonly class Grounding
{
    /**
     * @param SuiteSummary|null $suite the last known suite state, when the command asked for it
     * @param string|null $recentFeature project-relative path of the most recently modified feature
     * @param string|null $recentSource project-relative path of the most recently modified source file
     * @param string $tree a compact listing of the project's source/spec/feature files
     * @param array<string, string> $namedFiles relative path => contents, for files the instruction names
     */
    public function __construct(
        public ?SuiteSummary $suite = null,
        public ?string $recentFeature = null,
        public ?string $recentSource = null,
        public string $tree = '',
        public array $namedFiles = [],
    ) {}

    /**
     * A grounding with nothing in it, for commands (and specs) that start blind.
     */
    public static function empty(): self
    {
        return new self();
    }
}
