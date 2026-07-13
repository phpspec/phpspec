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

use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;

/**
 * @internal
 * The plain-text grounding a turn hands the AI: the suite's real red/green
 * state, the failing and pending examples with their errors, and a one-line
 * reminder of the role being played. ANSI-free and compact, it is the single
 * source of the state the model sees — the same reality the human does — so it
 * stops guessing at what the suite is doing.
 */
final readonly class SituationReport
{
    private function __construct(
        private ?SuiteSummary $summary,
        private PairRole $role,
    ) {}

    /**
     * Builds the report from the latest run outcome (which may be null before
     * anything has run, or carry no summary) and the role in force this turn.
     */
    public static function fromOutcome(?RunOutcome $outcome, PairRole $role): self
    {
        return new self($outcome?->summary, $role);
    }

    /**
     * Builds the report straight from a suite summary (which may be null before
     * anything has run) and the role in force this turn.
     */
    public static function fromSummary(?SuiteSummary $summary, PairRole $role): self
    {
        return new self($summary, $role);
    }

    /**
     * Renders the grounding block: a SUITE line, any FAILING/PENDING examples,
     * and the role note. No ANSI, one fact per line.
     */
    public function render(): string
    {
        $lines = [$this->suiteLine()];

        foreach ($this->exampleLines('FAILING', $this->summary?->failing() ?? []) as $line) {
            $lines[] = $line;
        }

        foreach ($this->exampleLines('PENDING', $this->summary?->pending() ?? []) as $line) {
            $lines[] = $line;
        }

        $lines[] = $this->roleNote();

        return implode("\n", $lines);
    }

    /**
     * The one-line summary of where the suite stands.
     */
    private function suiteLine(): string
    {
        if ($this->summary === null) {
            return 'SUITE: not run yet this session.';
        }

        if ($this->summary->isEmpty()) {
            return 'SUITE: green — no examples yet.';
        }

        $c = $this->summary->counts();
        $breakdown = sprintf(
            '%d passed, %d failed, %d errored, %d pending',
            $c['passes'],
            $c['failures'],
            $c['errors'],
            $c['pending'],
        );

        return sprintf('SUITE: %s — %d examples: %s', $this->summary->status(), $c['examples'], $breakdown);
    }

    /**
     * Renders a labelled list of example tuples, or nothing when the list is
     * empty. Each example reads "subject › example" with its error appended when
     * one is present.
     *
     * @param list<array{subject: string, example: string, error: string}> $examples
     * @return list<string>
     */
    private function exampleLines(string $label, array $examples): array
    {
        if ($examples === []) {
            return [];
        }

        $lines = [$label . ':'];
        foreach ($examples as $example) {
            $line = sprintf('  - %s › %s', $example['subject'], $example['example']);
            if ($example['error'] !== '') {
                $line .= ' — ' . $example['error'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * The one-line reminder of the contract for the role in force this turn.
     */
    private function roleNote(): string
    {
        return $this->role->aiIsDriver()
            ? 'NOTE: You are driving — state your one-step plan, make exactly that change, then present the diff and the new red/green state.'
            : 'NOTE: You are navigating — review the step, draw out the intent, and flag any rule or design break; do not write files.';
    }
}
