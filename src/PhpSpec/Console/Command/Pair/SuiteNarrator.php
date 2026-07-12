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

        $lines = [''];

        if ($summary === null || $summary->isEmpty()) {
            if ($aiAvailable) {
                $lines[] = '  Nothing here yet. So — what are we building?';
                $lines[] = '  <fg=gray>Describe it in a sentence and we\'ll turn it into a spec together.</>';
            } else {
                $lines[] = '  Nothing here yet. Let\'s start with a spec.';
                $lines[] = '  <fg=gray>Try <fg=white>describe App\\Something</> to write your first one.</>';
            }
        } elseif ($summary->isRed()) {
            $failure = $summary->failing()[0] ?? null;
            if ($failure !== null) {
                $lines[] = sprintf(
                    '  <fg=red>We\'re red</> on <options=bold>%s</> — %s',
                    $failure['subject'],
                    $failure['example'],
                );
                $lines[] = '  <fg=gray>Want to start there?</>';
            } else {
                $lines[] = '  <fg=red>We\'re red.</>';
            }
        } else {
            $gap = $summary->nearestPendingGap();
            if ($gap !== null) {
                $lines[] = sprintf(
                    '  <fg=green>Green.</> Nearest gap — <options=bold>%s</>: %s',
                    $gap['subject'],
                    $gap['example'],
                );
                $lines[] = '  <fg=gray>Shall we make it real?</>';
            } else {
                $lines[] = '  <fg=green>Green</> and clean. Your call.';
            }
        }

        $lines[] = '';
        $lines[] = $aiAvailable ? self::FOOTER_AI : self::FOOTER_COMMANDS;

        return $lines;
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
        $driving = $role->aiIsDriver();

        if ($summary === null || $summary->isEmpty()) {
            return [
                'lines' => ['', '  Nothing to build on yet. Let\'s <fg=white>describe</> the first spec.'],
                'action' => 'describe',
                'target' => null,
            ];
        }

        if ($summary->isRed()) {
            $failure = $summary->failing()[0] ?? ['subject' => '', 'example' => ''];
            $line = $driving
                ? sprintf('  We\'re red on <options=bold>%s</> — %s. I\'ll run it and generate what\'s missing.', $failure['subject'], $failure['example'])
                : sprintf('  We\'re red on <options=bold>%s</> — %s. Type <fg=white>run</> and I\'ll offer to create what\'s missing.', $failure['subject'], $failure['example']);

            return ['lines' => ['', $line], 'action' => 'run', 'target' => $failure['subject']];
        }

        $gap = $summary->nearestPendingGap();
        if ($gap !== null) {
            $line = $driving
                ? sprintf('  Green. Nearest gap — <options=bold>%s</>: %s. I\'ll make it real.', $gap['subject'], $gap['example'])
                : sprintf('  Green. Nearest gap — <options=bold>%s</>: %s. Let\'s exemplify it.', $gap['subject'], $gap['example']);

            return ['lines' => ['', $line], 'action' => 'exemplify', 'target' => $gap['subject']];
        }

        return [
            'lines' => ['', '  Green and clean. What shall we build next?'],
            'action' => 'observe',
            'target' => null,
        ];
    }
}
