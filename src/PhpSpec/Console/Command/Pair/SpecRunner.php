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

namespace PhpSpec\Console\Command\Pair;

use PhpSpec\Console\Command\Run\GenerationCandidates;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Runs the specs for a pair-mode `run` in isolation from the REPL process and
 * reports back what could be generated. Injected into CommandDispatcher so the
 * REPL can be unit-tested without actually running specs.
 */
interface SpecRunner
{
    /**
     * Runs the specs addressed by $argument (empty means all), streaming the
     * run's output to $output.
     *
     * @param string $argument the run argument (a path, or empty for all)
     * @param OutputInterface $output the output to stream the run into
     * @return GenerationCandidates|null the reported candidates, or null when none
     */
    public function run(string $argument, OutputInterface $output): ?GenerationCandidates;
}
