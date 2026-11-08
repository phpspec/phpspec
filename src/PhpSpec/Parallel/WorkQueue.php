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

namespace PhpSpec\Parallel;

/**
 * @internal
 * The files a queued worker is handed, read off its standard input as the
 * run that started it hands each over. Once the worker is through with one,
 * it says it ran it, so it is handed the next, until none is left.
 *
 * @implements \IteratorAggregate<int, string>
 */
final class WorkQueue implements \IteratorAggregate
{
    private readonly Wire $wire;

    /**
     * @param resource $input where the files arrive, a line each
     * @param WireFormatter $formatter where the worker says it ran each
     */
    public function __construct(
        private $input,
        private readonly WireFormatter $formatter,
    ) {
        $this->wire = new Wire();
    }

    /**
     * @return \Generator<int, string> each file's targets, comma-separated as the loader takes them
     */
    public function getIterator(): \Generator
    {
        while (($line = fgets($this->input)) !== false) {
            $targets = $this->wire->given(rtrim($line, "\r\n"));

            if ($targets === null) {
                continue;
            }

            yield implode(',', $targets);

            $this->formatter->ran();
        }
    }
}
