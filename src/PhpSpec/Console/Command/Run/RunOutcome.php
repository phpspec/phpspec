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

namespace PhpSpec\Console\Command\Run;

/**
 * @internal
 * The result of a pair-mode run carried back from the subprocess: the code
 * generation candidates (what could be generated) and the suite summary (what
 * the run actually did — red/green, counts, failing/pending). Either may be null.
 */
final readonly class RunOutcome
{
    public function __construct(
        public ?GenerationCandidates $candidates = null,
        public ?SuiteSummary $summary = null,
    ) {}

    /**
     * Returns whether there is nothing to generate from this run.
     */
    public function isEmptyCandidates(): bool
    {
        return $this->candidates?->isEmpty() ?? true;
    }
}
