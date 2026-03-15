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

/**
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
