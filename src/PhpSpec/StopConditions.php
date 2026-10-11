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

namespace PhpSpec;

use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\StepResult;

/**
 * @internal
 * Value object specifying which result states should halt the suite early.
 */
final readonly class StopConditions
{
    /**
     * @param bool $onFailure whether to stop on example failures
     * @param bool $onError whether to stop on errors
     * @param bool $onWarning whether to stop on warnings
     * @param bool $onDeprecation whether to stop on deprecation notices
     * @param bool $onNotice whether to stop on PHP notices
     * @param bool $onPending whether to stop on pending examples
     * @param bool $onSkipped whether to stop on skipped examples
     */
    public function __construct(
        public bool $onFailure = false,
        public bool $onError = false,
        public bool $onWarning = false,
        public bool $onDeprecation = false,
        public bool $onNotice = false,
        public bool $onPending = false,
        public bool $onSkipped = false,
    ) {}

    /**
     * Returns true if any stop condition is enabled.
     */
    public function any(): bool
    {
        return $this->onFailure
            || $this->onError
            || $this->onWarning
            || $this->onDeprecation
            || $this->onNotice
            || $this->onPending
            || $this->onSkipped;
    }

    /**
     * Whether this result, or any result under it, is of a kind to stop on.
     */
    public function metBy(Results $result): bool
    {
        if ($result instanceof ExampleResult) {
            return ($this->onFailure && ($result->isFailure() || $result->isError()))
                || ($this->onError && $result->isError())
                || $this->metByNotesOf($result)
                || ($this->onPending && $result->isPending())
                || ($this->onSkipped && $result->isSkipped());
        }

        if ($result instanceof StepResult) {
            return ($this->onFailure && ($result->isFailure() || $result->isError()))
                || ($this->onError && $result->isError())
                || $this->metByNotesOf($result)
                || ($this->onPending && ($result->isPending() || $result->isUndefined()))
                || ($this->onSkipped && $result->isSkippedForAReason());
        }

        foreach ($result->getResults() as $child) {
            if ($this->metBy($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The option a result reached, the first of them when it reached several,
     * or null when it reached none.
     */
    public function reachedBy(Results $result): ?string
    {
        foreach ($this->options() as $option) {
            if ($this->alone($option)->metBy($result)) {
                return $option;
            }
        }

        return null;
    }

    private function alone(string $option): self
    {
        return match ($option) {
            '--stop-on-failure' => new self(onFailure: true),
            '--stop-on-error' => new self(onError: true),
            '--stop-on-warning' => new self(onWarning: true),
            '--stop-on-deprecation' => new self(onDeprecation: true),
            '--stop-on-notice' => new self(onNotice: true),
            '--stop-on-pending' => new self(onPending: true),
            default => new self(onSkipped: true),
        };
    }

    /**
     * The run options that would set these conditions again, for a worker
     * process to halt as its parent would.
     *
     * @return list<string>
     */
    public function options(): array
    {
        return array_keys(array_filter([
            '--stop-on-failure' => $this->onFailure,
            '--stop-on-error' => $this->onError,
            '--stop-on-warning' => $this->onWarning,
            '--stop-on-deprecation' => $this->onDeprecation,
            '--stop-on-notice' => $this->onNotice,
            '--stop-on-pending' => $this->onPending,
            '--stop-on-skipped' => $this->onSkipped,
        ]));
    }

    /**
     * Creates a StopConditions that stops on any non-pass result.
     */
    public static function fromProblems(): self
    {
        return new self(
            onFailure: true,
            onError: true,
            onWarning: true,
            onDeprecation: true,
            onNotice: true,
            onPending: true,
            onSkipped: true,
        );
    }

    private function metByNotesOf(ExampleResult|StepResult $result): bool
    {
        return ($this->onWarning && $result->hasWarnings())
            || ($this->onDeprecation && $result->hasDeprecations())
            || ($this->onNotice && $result->hasNotices());
    }
}
