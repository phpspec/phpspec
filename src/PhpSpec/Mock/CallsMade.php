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

namespace PhpSpec\Mock;

use Closure;
use LogicException;

/**
 * @internal
 * What toHaveBeenCalled() hands back, so a count can follow it: once(),
 * twice(), exactly(N)->times(), atLeast(N)->times() or atMost(N)->times(),
 * each judged at once from the calls made so far, as the check itself was.
 */
final class CallsMade
{
    private ?string $constraint = null;

    private int $count = 0;

    /**
     * @param Closure(string, int, string, int): void $judge judges a constraint and a count, at the file and line they were written
     */
    public function __construct(private readonly Closure $judge) {}

    public function once(): void
    {
        $this->judgedHere('exactly', 1);
    }

    public function twice(): void
    {
        $this->judgedHere('exactly', 2);
    }

    public function exactly(int $n): self
    {
        return $this->pending('exactly', $n);
    }

    public function atLeast(int $n): self
    {
        return $this->pending('atLeast', $n);
    }

    public function atMost(int $n): self
    {
        return $this->pending('atMost', $n);
    }

    public function times(): void
    {
        if ($this->constraint === null) {
            throw new LogicException('times() follows exactly(), atLeast() or atMost().');
        }

        $this->judgedHere($this->constraint, $this->count);
    }

    private function pending(string $constraint, int $count): self
    {
        $this->constraint = $constraint;
        $this->count = $count;

        return $this;
    }

    private function judgedHere(string $constraint, int $count): void
    {
        $site = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];

        ($this->judge)($constraint, $count, (string) ($site['file'] ?? 'unknown'), (int) ($site['line'] ?? 0));
    }
}
