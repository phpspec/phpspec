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

namespace PhpSpec\Console\Command\Refactor;

/**
 * @internal
 * A refactoring the model means to make: the technique, why, in its own
 * words, and the baby steps it takes, in order.
 */
final readonly class RefactorPlan
{
    /**
     * @param list<PlannedStep> $steps
     * @param int $tokens how many tokens the model wrote for it
     * @param array<string, string> $read each file the plan was made reading, project-relative, with a sha1 of its content
     */
    public function __construct(
        public string $technique,
        public string $rationale,
        public array $steps,
        public int $tokens = 0,
        public array $read = [],
    ) {}

    /**
     * The plan as it was kept by toArray().
     *
     * @param array<string, mixed> $kept
     */
    public static function fromArray(array $kept): self
    {
        $steps = [];

        foreach (is_array($kept['steps'] ?? null) ? $kept['steps'] : [] as $step) {
            $steps[] = new PlannedStep((string) ($step['title'] ?? ''), (string) ($step['doing'] ?? ''));
        }

        return new self((string) ($kept['technique'] ?? ''), (string) ($kept['rationale'] ?? ''), $steps);
    }

    /**
     * The plan as the model proposed it, to keep or to hand back to it.
     *
     * @return array{technique: string, rationale: string, steps: list<array{title: string, doing: string}>}
     */
    public function toArray(): array
    {
        return [
            'technique' => $this->technique,
            'rationale' => $this->rationale,
            'steps' => array_map(static fn(PlannedStep $step): array => ['title' => $step->title, 'doing' => $step->doing], $this->steps),
        ];
    }
}
