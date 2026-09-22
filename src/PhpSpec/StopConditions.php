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
     * @param bool $onSkipped whether to stop on skipped examples
     */
    public function __construct(
        public bool $onFailure = false,
        public bool $onError = false,
        public bool $onWarning = false,
        public bool $onDeprecation = false,
        public bool $onNotice = false,
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
                || ($this->onWarning && $result->hasWarnings())
                || ($this->onDeprecation && $result->hasDeprecations())
                || ($this->onNotice && $result->hasNotices())
                || ($this->onSkipped && $result->isSkipped());
        }

        if ($result instanceof StepResult) {
            return ($this->onFailure && ($result->isFailure() || $result->isError()))
                || ($this->onError && $result->isError());
        }

        foreach ($result->getResults() as $child) {
            if ($this->metBy($child)) {
                return true;
            }
        }

        return false;
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
            onSkipped: true,
        );
    }
}
