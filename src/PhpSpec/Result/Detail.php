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

namespace PhpSpec\Result;

/**
 * Base class for match result details, representing either a successful or failed match.
 *
 * Uses a sentinel value (Nothing) to distinguish between "no failure" and a null failure message.
 */
abstract class Detail
{
    /** Sentinel value indicating no failure occurred */
    public const Nothing = '@PhpSpec\Result\Detail::Nothing@';

    /**
     * @param mixed $failure the failure message, or the Nothing sentinel for success
     */
    protected function __construct(protected mixed $failure = Detail::Nothing) {}

    /**
     * Checks whether this detail represents a successful match.
     */
    public function isSuccessful(): bool
    {
        return $this->failure === Detail::Nothing;
    }

    /**
     * Checks whether this detail represents a failed match.
     */
    public function isFailure(): bool
    {
        return !$this->isSuccessful();
    }

    /**
     * Returns the failure message, or the Nothing sentinel if successful.
     */
    public function getFailure(): mixed
    {
        return $this->failure;
    }

    /**
     * Returns the surrounding source code lines at the failure site.
     *
     * @return array empty by default; overridden in Failed subclass
     */
    public function getSurroundingCode(): array
    {
        return [];
    }

    /**
     * Returns the line number where the failure occurred, or null if not applicable.
     */
    public function getLine(): ?int
    {
        return null;
    }

    /**
     * Returns the file path where the failure occurred, or null if not applicable.
     */
    public function getFile(): ?string
    {
        return null;
    }
}
