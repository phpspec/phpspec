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
 * Turns a run outcome into what a pair opens with: one observation drawn from
 * the real suite state — the failure to start on, the nearest pending gap, or a
 * clean-slate invitation — never a menu. Output uses Symfony style tags only, so
 * it degrades cleanly with no ANSI (CI, screen readers).
 */
final class SuiteNarrator
{
    private const FOOTER_AI = '  <fg=gray>plain English works · next · /swap · /help · /quit</>';
    private const FOOTER_COMMANDS = '  <fg=gray>describe · exemplify · run · next · /swap · /help · /quit</>';

    /**
     * The opening lines for a pairing session, chosen by suite state.
     *
     * The observation (red/green/pending) is the same either way; the invitation
     * and footer adapt to whether an AI provider is configured — with AI we can
     * offer plain English, without it we steer to the deterministic commands.
     *
     * @param bool $aiAvailable whether an AI provider is configured
     * @return list<string>
     */
    public function greeting(?RunOutcome $outcome, bool $aiAvailable): array
    {
        $summary = $outcome?->summary;

        $observation = match (true) {
            $summary === null || $summary->isEmpty() => $this->emptyProjectGreeting($aiAvailable),
            $summary->isRed() => $this->redGreeting($summary),
            default => $this->greenGreeting($summary),
        };

        $footer = $aiAvailable ? self::FOOTER_AI : self::FOOTER_COMMANDS;

        return ['', ...$observation, '', $footer];
    }

    /**
     * @return list<string>
     */
    private function emptyProjectGreeting(bool $aiAvailable): array
    {
        if ($aiAvailable) {
            return [
                '  Nothing here yet. So — what are we building?',
                '  <fg=gray>Describe it in a sentence and we\'ll turn it into a spec together.</>',
            ];
        }

        return [
            '  Nothing here yet. Let\'s start with a spec.',
            '  <fg=gray>Try <fg=white>describe App\\Something</> to write your first one.</>',
        ];
    }

    /**
     * @return list<string>
     */
    private function redGreeting(SuiteSummary $summary): array
    {
        $failure = $summary->failing()[0] ?? null;

        if ($failure === null) {
            return ['  <fg=red>We\'re red.</>'];
        }

        return [
            sprintf('  <fg=red>We\'re red</> on <options=bold>%s</> — %s', $failure['subject'], $failure['example']),
            '  <fg=gray>Want to start there?</>',
        ];
    }

    /**
     * @return list<string>
     */
    private function greenGreeting(SuiteSummary $summary): array
    {
        $gap = $summary->nearestPendingGap();

        if ($gap === null) {
            return ['  <fg=green>Green</> and clean. Your call.'];
        }

        return [
            sprintf('  <fg=green>Green.</> Nearest gap — <options=bold>%s</>: %s', $gap['subject'], $gap['example']),
            '  <fg=gray>Shall we make it real?</>',
        ];
    }

    /**
     * The single most valuable next step, drawn from real suite state. Red means
     * run and fix (never re-describe an existing spec); a pending example is the
     * nearest gap to make real; an empty project starts with a first spec. The
     * lines are phrased for the current role; the action lets the caller act when
     * the AI is driving.
     *
     * @return array{lines: list<string>, action: 'run'|'exemplify'|'describe'|'observe', target: ?string}
     */
    public function next(?RunOutcome $outcome, PairRole $role): array
    {
        $summary = $outcome?->summary;

        return match (true) {
            $summary === null || $summary->isEmpty() => $this->describeFirstStep(),
            $summary->isRed() => $this->runStep($summary, $role),
            $summary->nearestPendingGap() !== null => $this->exemplifyStep($summary, $role),
            default => $this->observeStep(),
        };
    }

    /**
     * @return array{lines: list<string>, action: 'describe', target: null}
     */
    private function describeFirstStep(): array
    {
        return [
            'lines' => ['', '  Nothing to build on yet. Let\'s <fg=white>describe</> the first spec.'],
            'action' => 'describe',
            'target' => null,
        ];
    }

    /**
     * @return array{lines: list<string>, action: 'run', target: string}
     */
    private function runStep(SuiteSummary $summary, PairRole $role): array
    {
        $failure = $summary->failing()[0] ?? ['subject' => '', 'example' => ''];

        $line = $role->aiIsDriver()
            ? sprintf('  We\'re red on <options=bold>%s</> — %s. I\'ll run it and generate what\'s missing.', $failure['subject'], $failure['example'])
            : sprintf('  We\'re red on <options=bold>%s</> — %s. Type <fg=white>run</> and I\'ll offer to create what\'s missing.', $failure['subject'], $failure['example']);

        return ['lines' => ['', $line], 'action' => 'run', 'target' => $failure['subject']];
    }

    /**
     * @return array{lines: list<string>, action: 'exemplify', target: string}
     */
    private function exemplifyStep(SuiteSummary $summary, PairRole $role): array
    {
        $gap = $summary->nearestPendingGap() ?? ['subject' => '', 'example' => ''];

        $line = $role->aiIsDriver()
            ? sprintf('  Green. Nearest gap — <options=bold>%s</>: %s. I\'ll make it real.', $gap['subject'], $gap['example'])
            : sprintf('  Green. Nearest gap — <options=bold>%s</>: %s. Let\'s exemplify it.', $gap['subject'], $gap['example']);

        return ['lines' => ['', $line], 'action' => 'exemplify', 'target' => $gap['subject']];
    }

    /**
     * @return array{lines: list<string>, action: 'observe', target: null}
     */
    private function observeStep(): array
    {
        return [
            'lines' => ['', '  Green and clean. What shall we build next?'],
            'action' => 'observe',
            'target' => null,
        ];
    }
}
