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

namespace PhpSpec\Extensions;

use PhpSpec\Result\Kind;
use PhpSpec\Result\Outcome;
use PhpSpec\Result\State;

/**
 * Base class for formatter extensions: each example and each scenario is
 * handed over as an Outcome, by how it ended. Subclasses implement getName(),
 * formatPass() and formatFail(); the rest fall back to those.
 */
abstract class FormatterExtension
{
    /**
     * The name used with --format.
     */
    abstract public function getName(): string;

    /**
     * An example or scenario that passed, or an example that checked nothing.
     */
    abstract public function formatPass(Outcome $outcome): string;

    /**
     * An example or scenario that failed, or a scenario with a step no
     * definition matches.
     */
    abstract public function formatFail(Outcome $outcome): string;

    public function formatError(Outcome $outcome): string
    {
        return $this->formatFail($outcome);
    }

    public function formatPending(Outcome $outcome): string
    {
        return 'PENDING: ' . $outcome->name();
    }

    public function formatSkipped(Outcome $outcome): string
    {
        return $this->formatPending($outcome);
    }

    /**
     * Formats an outcome by how it ended.
     */
    public function format(Outcome $outcome): string
    {
        return match ($outcome->state) {
            State::Passed, State::Risky => $this->formatPass($outcome),
            State::Failed, State::Undefined => $this->formatFail($outcome),
            State::Errored => $this->formatError($outcome),
            State::Pending => $this->formatPending($outcome),
            State::Skipped => $this->formatSkipped($outcome),
        };
    }

    /**
     * The line after every outcome: how many examples and scenarios there
     * were, and how they ended.
     *
     * @param list<Outcome> $outcomes
     */
    public function formatSummary(array $outcomes): string
    {
        $ended = $this->endings($outcomes);

        return implode(', ', $this->kinds($outcomes)) . ($ended === [] ? '' : ' (' . implode(', ', $ended) . ')');
    }

    /**
     * @param list<Outcome> $outcomes
     * @return list<string>
     */
    private function kinds(array $outcomes): array
    {
        $counted = [];

        foreach (Kind::cases() as $kind) {
            $count = count(array_filter($outcomes, static fn(Outcome $outcome): bool => $outcome->kind === $kind));
            if ($count > 0 || ($outcomes === [] && $kind === Kind::Example)) {
                $counted[] = $count . ' ' . $kind->value . ($count === 1 ? '' : 's');
            }
        }

        return $counted;
    }

    /**
     * @param list<Outcome> $outcomes
     * @return list<string>
     */
    private function endings(array $outcomes): array
    {
        $ended = [];

        foreach (State::cases() as $state) {
            $count = count(array_filter($outcomes, static fn(Outcome $outcome): bool => $outcome->state === $state));
            if ($count > 0) {
                $ended[] = $count . ' ' . $state->value;
            }
        }

        return $ended;
    }
}
