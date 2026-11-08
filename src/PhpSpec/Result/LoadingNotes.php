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
 * What files raised as they loaded, across the results of several processes.
 * Each process loads a file once, so a run says it once, as it would in one
 * process, on the first result that arrives with it.
 */
final class LoadingNotes
{
    /** @var array<string, true> */
    private array $said = [];

    public function keepFirstIn(Results $result): void
    {
        if ($result instanceof ExampleResult || $result instanceof StepResult) {
            $result->keepNotes($this->unsaid(...));

            return;
        }

        foreach ($result->getResults() as $inner) {
            $this->keepFirstIn($inner);
        }
    }

    /**
     * @param array{severity: int, message: string, file: string, line: int, loading?: true} $note
     */
    private function unsaid(array $note): bool
    {
        if (!isset($note['loading'])) {
            return true;
        }

        $key = $note['message'] . ':' . $note['file'] . ':' . $note['line'];
        $said = isset($this->said[$key]);
        $this->said[$key] = true;

        return !$said;
    }
}
