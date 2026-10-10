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

namespace PhpSpec\StoryBDD;

use PhpSpec\Result\StepResult;
use PhpSpec\Specification\LateSignal;
use PhpSpec\Specification\PendingException;
use PhpSpec\Specification\SkippedException;
use Throwable;

/**
 * @internal
 * What a story hook's skip(), pending() or error does to the steps around it.
 * A before-hook decides the outcome of the first step it kept from running; an
 * after-hook breaks the step that just ran, or warns on it when that step had
 * already broken, so a hook never ends the run and never hides a failure.
 */
final readonly class HookOutcome
{
    /** For a signal that comes after what it meant to leave out: what ran, and where to call it instead. */
    private const LATE = [
        'afterStep' => ['the step', 'beforeStep or in the step'],
        'afterScenario' => ['the scenario', 'beforeScenario or in a step'],
        'afterFeature' => ['the feature', 'beforeFeature or in a step'],
    ];

    public function __construct(
        private Throwable $raised,
        private string $hook,
    ) {}

    /**
     * The step a before-hook kept from running: pending or skipped with the
     * hook's reason, or errored with its error.
     */
    public function instead(string $title): StepResult
    {
        if ($this->raised instanceof PendingException) {
            return new StepResult($title, 'pending', $this->raised->getMessage());
        }

        if ($this->raised instanceof SkippedException) {
            return new StepResult($title, 'skipped', $this->raised->getMessage());
        }

        $result = new StepResult($title, 'error');
        $result->setError($this->error());

        return $result;
    }

    /**
     * The outcome alone, for a scenario with no step to carry it.
     */
    public function alone(): StepResult
    {
        return $this->instead($this->hook);
    }

    /**
     * The step an after-hook followed: errored by the hook, or, when it had
     * already failed or errored, keeping its own outcome with the hook's error
     * as a warning under it.
     */
    public function after(StepResult $step): StepResult
    {
        $error = $this->error();

        if ($step->isFailure() || $step->isError()) {
            $step->setWarnings([...$step->getWarnings(), [
                'severity' => E_USER_WARNING,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ]]);

            return $step;
        }

        $broken = new StepResult($step->getTitle(), 'error');
        $broken->setError($error);
        $broken->setOutput($step->getOutput());
        $broken->setDuration($step->getDuration());
        $broken->setWarnings($step->getWarnings());

        return $broken;
    }

    private function error(): StepError
    {
        if (($this->raised instanceof PendingException || $this->raised instanceof SkippedException) && isset(self::LATE[$this->hook])) {
            [$ran, $instead] = self::LATE[$this->hook];

            return new StepError((new LateSignal($this->raised, $this->hook, $ran, $instead))->sentence(), $this->raised);
        }

        return new StepError(sprintf('%s (in %s)', $this->raised->getMessage(), $this->hook), $this->raised);
    }
}
