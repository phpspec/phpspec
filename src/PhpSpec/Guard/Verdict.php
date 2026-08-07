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
 *
 * Three conclusions, not two. "I could not judge" is not "nothing was wrong":
 * a guard that stops judging in silence is worse than no guard at all, because
 * everybody carries on believing they have one.
 */
final readonly class Verdict
{
    /**
     * @param list<Violation> $violations
     */
    private function __construct(
        private array $violations,
        private ?string $reason = null,
    ) {}

    /**
     * @param list<Violation> $violations
     */
    public static function of(array $violations): self
    {
        return new self($violations);
    }

    public static function clean(): self
    {
        return new self([]);
    }

    /**
     * Judged nothing, and why. The reason is a whole sentence because it is
     * printed as one, and it names what the reader should do next.
     */
    public static function cannotJudge(string $reason): self
    {
        return new self([], $reason);
    }

    /**
     * Whether guard reached a conclusion at all. Ask this before `held()`: an
     * unjudged verdict holds only in the sense that it accuses nobody.
     */
    public function judged(): bool
    {
        return $this->reason === null;
    }

    public function reason(): ?string
    {
        return $this->reason;
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
        $document = [
            'held' => $this->held(),
            'judged' => $this->judged(),
            'violations' => array_map(static fn(Violation $violation) => $violation->toArray(), $this->violations),
        ];

        return $this->reason === null ? $document : $document + ['reason' => $this->reason];
    }
}
