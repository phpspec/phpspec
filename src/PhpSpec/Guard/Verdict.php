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

namespace PhpSpec\Guard;

/**
 * @internal
 * What guard concluded, said once and read by everyone: the console line, the
 * exit code and the agent document all derive from this, so they cannot
 * disagree about whether the cycle held.
 */
final readonly class Verdict
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(private array $violations) {}

    public static function clean(): self
    {
        return new self([]);
    }

    public function held(): bool
    {
        return $this->violations === [];
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * The verdict as data, for the agent document.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'held' => $this->held(),
            'violations' => array_map(static fn(Violation $violation) => $violation->toArray(), $this->violations),
        ];
    }
}
