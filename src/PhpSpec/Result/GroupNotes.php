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

namespace PhpSpec\Result;

use PhpSpec\Results;

/**
 * @internal
 * What a group's own code raised outside its examples and steps: a spec file
 * as it loads, a describe body and its beforeAll and afterAll, a feature's
 * steps and support files and its beforeFeature and afterFeature. What set the
 * group up goes on the first example or step that ran after it, what tore it
 * down on the last that ran, so each is reported where every format already
 * shows notes. A group nothing ran in has nowhere to report them.
 */
final readonly class GroupNotes
{
    /**
     * @param list<array{severity: int, message: string, file: string, line: int}> $setup
     * @param list<array{severity: int, message: string, file: string, line: int}> $teardown
     */
    public function __construct(
        private array $setup,
        private array $teardown = [],
    ) {}

    /**
     * @param array<Results> $results what the group ran, in order
     */
    public function settleOn(array $results): void
    {
        if ($this->setup !== []) {
            $this->first($results)?->raised($this->setup);
        }

        if ($this->teardown !== []) {
            $this->first(array_reverse($results), last: true)?->raised($this->teardown);
        }
    }

    /**
     * @param array<Results> $results
     */
    private function first(array $results, bool $last = false): ExampleResult|StepResult|null
    {
        foreach ($results as $result) {
            if ($result instanceof ExampleResult || $result instanceof StepResult) {
                return $result;
            }

            $inner = $result->getResults();
            $found = $this->first($last ? array_reverse($inner) : $inner, $last);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
