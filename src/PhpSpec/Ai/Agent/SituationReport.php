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

namespace PhpSpec\Ai\Agent;

use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;

/**
 * @internal
 * The plain-text grounding a turn hands the AI: the suite's real red/green
 * state, with the failing and pending examples and their errors. ANSI-free and
 * compact, it is the single source of the state the model sees (the same
 * reality the human does), so it stops guessing at what the suite is doing.
 * The role contract lives in the role manifests, not here.
 */
final readonly class SituationReport
{
    private function __construct(
        private ?SuiteSummary $summary,
    ) {}

    /**
     * Builds the report from the latest run outcome, which may be null before
     * anything has run, or carry no summary.
     */
    public static function fromOutcome(?RunOutcome $outcome): self
    {
        return new self($outcome?->summary);
    }

    /**
     * Builds the report straight from a suite summary, which may be null
     * before anything has run.
     */
    public static function fromSummary(?SuiteSummary $summary): self
    {
        return new self($summary);
    }

    /**
     * Renders the grounding block: a SUITE line and any FAILING/PENDING
     * examples. No ANSI, one fact per line.
     */
    public function render(): string
    {
        $lines = [$this->suiteLine()];

        foreach ($this->featureLines() as $line) {
            $lines[] = $line;
        }

        foreach ($this->exampleLines('FAILING', $this->summary?->failing() ?? []) as $line) {
            $lines[] = $line;
        }

        foreach ($this->exampleLines('PENDING', $this->summary?->pending() ?? []) as $line) {
            $lines[] = $line;
        }

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
     * The feature (story) grounding: a FEATURES tally line, then each non-green
     * feature named so the model knows which scenario to drive. Empty for a
     * spec-only suite, so nothing about features leaks into that report.
     *
     * @return list<string>
     */
    private function featureLines(): array
    {
        if ($this->summary === null || !$this->summary->hasFeatures()) {
            return [];
        }

        $c = $this->summary->featureCounts();
        $lines = [sprintf(
            'FEATURES: %d features, %d scenarios, %d steps (%d failing, %d undefined)',
            $c['features'],
            $c['scenarios'],
            $c['steps'],
            $c['stepFailures'],
            $c['undefined'],
        )];

        foreach ($this->summary->features() as $feature) {
            if ($feature['status'] === 'red') {
                $lines[] = sprintf('  - red scenario in %s', $feature['path']);
            } elseif ($feature['status'] === 'todo') {
                $lines[] = sprintf('  - steps to write in %s (%d undefined)', $feature['path'], $feature['undefined']);
            }
        }

        return $lines;
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
}
