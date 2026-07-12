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

use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 * Opens a pairing session by looking at the project once: it runs the suite
 * quietly (swallowing the dot output) and renders the SuiteNarrator's greeting
 * into the pair screen, so the session starts from real red/green state rather
 * than a static menu.
 */
final readonly class Greeter
{
    /**
     * @param SpecRunner $specRunner runs the suite to learn its state
     * @param PairOutput $output the pair screen to greet into
     * @param bool $aiAvailable whether an AI provider is configured
     * @param SuiteNarrator $narrator maps suite state to the greeting lines
     */
    public function __construct(
        private SpecRunner $specRunner,
        private PairOutput $output,
        private bool $aiAvailable,
        private SuiteNarrator $narrator = new SuiteNarrator(),
    ) {}

    /**
     * Opens the session by running the suite once, quietly, and printing the
     * greeting drawn from its state.
     */
    public function greet(): void
    {
        $outcome = $this->specRunner->run('', new BufferedOutput());

        $console = $this->output->getOutput();
        foreach ($this->narrator->greeting($outcome, $this->aiAvailable) as $line) {
            $console->writeln($line);
        }
    }
}
