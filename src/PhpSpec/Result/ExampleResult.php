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

use PhpSpec\Results;
use PhpSpec\Specification\ExampleError;

/**
 * @internal
 * Holds the result of a single example (it() block), including match results,
 * error/pending/skipped state, execution duration, and optional warnings/deprecations/notices.
 */
final class ExampleResult implements Results
{
    /** @var ExampleError|null Error that occurred during execution */
    private ?ExampleError $error = null;

    /** @var float Execution duration in seconds */
    private float $duration = 0.0;

    /** @var array<array{severity: int, message: string, file: string, line: int}> Warning items (E_WARNING, E_USER_WARNING) collected during execution */
    private array $warnings = [];

    /** @var array<array{severity: int, message: string, file: string, line: int}> Deprecation items (E_DEPRECATED, E_USER_DEPRECATED) collected during execution */
    private array $deprecations = [];

    /** @var array<array{severity: int, message: string, file: string, line: int}> Notice items (E_NOTICE, E_USER_NOTICE) collected during execution */
    private array $notices = [];

    /** @var string What the subject printed while this example ran */
    private string $output = '';

    /** @var array<string, string|array{error: string}> Context the example handed over about itself */
    private array $attachments = [];

    /**
     * @param string $title the example description
     * @param array<MatchResult> $matchResults array of MatchResult instances from this example
     * @param bool $isError whether the example errored
     * @param bool $isPending whether the example is pending
     * @param bool $isSkipped whether the example is skipped
     */
    public function __construct(
        private readonly string $title,
        private readonly array $matchResults,
        private readonly bool $isError = false,
        private readonly bool $isPending = false,
        private readonly bool $isSkipped = false,
    ) {}

    /**
     * Records the execution duration of this example.
     *
     * @param float $duration elapsed time in seconds
     */
    public function setDuration(float $duration): void
    {
        $this->duration = $duration;
    }

    /**
     * Returns the execution duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Returns the example description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the MatchResult instances from this example.
     *
     * @return MatchResult[]
     */
    public function getResults(): array
    {
        return $this->matchResults;
    }

    /**
     * Checks whether any match result in this example is a failure.
     */
    public function isFailure(): bool
    {
        foreach ($this->matchResults as $matchResult) {
            if ($matchResult->isFailure()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the message from the first failed match result, or empty string if none.
     */
    public function getFailureMessage(): string
    {
        foreach ($this->matchResults as $matchResult) {
            if ($matchResult->isFailure()) {
                return (string) $matchResult->getMessage();
            }
        }
        return '';
    }

    /**
     * Returns the error or first failure message, or empty string if passed/pending.
     */
    public function getMessage(): string
    {
        if ($this->isError && $this->error !== null) {
            return $this->error->getMessage();
        }
        return $this->getFailureMessage();
    }

    /**
     * Checks whether this example errored during execution.
     */
    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * Stores the error that caused this example to fail.
     *
     * @param ExampleError $error the error details
     */
    public function setError(ExampleError $error): void
    {
        $this->error = $error;
    }

    /**
     * Returns the error that caused this example to fail.
     */
    public function getError(): ?ExampleError
    {
        return $this->error;
    }

    /**
     * Checks whether this example is pending.
     */
    public function isPending(): bool
    {
        return $this->isPending;
    }

    /**
     * Checks whether this example was skipped.
     */
    public function isSkipped(): bool
    {
        return $this->isSkipped;
    }

    /**
     * Stores warning items collected during example execution.
     *
     * @param array<array{severity: int, message: string, file: string, line: int}> $warnings array of warning items
     */
    public function setWarnings(array $warnings): void
    {
        $this->warnings = $warnings;
    }

    /**
     * Returns the warning items from this example.
     *
     * @return array<array{severity: int, message: string, file: string, line: int}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Checks whether this example produced any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Stores deprecation items collected during example execution.
     *
     * @param array<array{severity: int, message: string, file: string, line: int}> $deprecations array of deprecation items
     */
    public function setDeprecations(array $deprecations): void
    {
        $this->deprecations = $deprecations;
    }

    /**
     * Returns the deprecation items from this example.
     *
     * @return array<array{severity: int, message: string, file: string, line: int}>
     */
    public function getDeprecations(): array
    {
        return $this->deprecations;
    }

    /**
     * Checks whether this example produced any deprecations.
     */
    public function hasDeprecations(): bool
    {
        return !empty($this->deprecations);
    }

    /**
     * Stores notice items collected during example execution.
     *
     * @param array<array{severity: int, message: string, file: string, line: int}> $notices array of notice items
     */
    public function setNotices(array $notices): void
    {
        $this->notices = $notices;
    }

    /**
     * Returns the notice items from this example.
     *
     * @return array<array{severity: int, message: string, file: string, line: int}>
     */
    public function getNotices(): array
    {
        return $this->notices;
    }

    /**
     * Checks whether this example produced any notices.
     */
    public function hasNotices(): bool
    {
        return !empty($this->notices);
    }

    /**
     * Stores what the subject printed while the example ran, which is a
     * diagnostic about this example and not a stray line on the terminal.
     *
     * @param string $output everything the example's code printed
     */
    public function setOutput(string $output): void
    {
        $this->output = $output;
    }

    /**
     * Returns what the subject printed while the example ran.
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Stores context the example handed over about itself, read before its
     * teardown ran.
     *
     * @param array<string, string|array{error: string}> $attachments
     */
    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    /**
     * Returns the context the example handed over about itself.
     *
     * @return array<string, string|array{error: string}>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Indicates this result represents an example (not a context).
     */
    public function isContext(): bool
    {
        return false;
    }
}
