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

use PhpSpec\ProjectRoot;
use PhpSpec\Results;
use PhpSpec\Specification\ExampleError;

/**
 * @internal
 * Holds the result of a single example (it() block), including match results,
 * error/pending/skipped state, execution duration, and optional warnings/deprecations/notices.
 */
final class ExampleResult implements Results
{
    use RaisedNotesTrait;

    /** @var ExampleError|null Error that occurred during execution */
    private ?ExampleError $error = null;

    /** @var float Execution duration in seconds */
    private float $duration = 0.0;

    /** @var string What the subject printed while this example ran */
    private string $output = '';

    /** @var array<string, string|array{error: string}> Context the example handed over about itself */
    private array $attachments = [];

    /** @var string|null The file the example is declared in */
    private ?string $file = null;

    /** @var int|null The line the example is declared on */
    private ?int $line = null;

    private bool $risky = false;

    private bool $leftOutByFocus = false;

    private bool $sharesItsLine = false;

    /**
     * @param string $title the example description
     * @param array<MatchResult> $matchResults array of MatchResult instances from this example
     * @param bool $isError whether the example errored
     * @param bool $isPending whether the example is pending
     * @param bool $isSkipped whether the example is skipped
     * @param string|null $reason why it was left pending or skipped, when it said
     */
    public function __construct(
        private readonly string $title,
        private readonly array $matchResults,
        private readonly bool $isError = false,
        private readonly bool $isPending = false,
        private readonly bool $isSkipped = false,
        private readonly ?string $reason = null,
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
     * Records where the example is declared, which is what addresses it when
     * nothing in it failed.
     */
    public function declaredAt(string $file, int $line): void
    {
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * Records that the line the example is declared on declares others too,
     * as an it() in a loop does.
     */
    public function markSharingItsLine(): void
    {
        $this->sharesItsLine = true;
    }

    public function sharesItsLine(): bool
    {
        return $this->sharesItsLine;
    }

    /**
     * Returns the file the example is declared in, or null when unknown.
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

    /**
     * Returns the line the example is declared on, or null when unknown.
     */
    public function getLine(): ?int
    {
        return $this->line;
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
     * How the example ended. Left out with pending() or skip() comes first, as
     * its body never ran; then broken, then failing, then checking nothing.
     */
    public function state(): State
    {
        return match (true) {
            $this->isPending => State::Pending,
            $this->isSkipped => State::Skipped,
            $this->isError => State::Errored,
            $this->isFailure() => State::Failed,
            $this->risky => State::Risky,
            default => State::Passed,
        };
    }

    /**
     * How the example ended, under the name it is given, its places named from
     * the project root.
     *
     * @param list<string> $titles the titles that lead to it, its own last
     */
    public function outcome(array $titles, ProjectRoot $root): Outcome
    {
        $state = $this->state();

        return new Outcome(
            Kind::Example,
            $titles,
            $state,
            $state === State::Failed || $state === State::Errored ? $this->getMessage() : null,
            $state === State::Risky ? 'no expectation' : $this->reason,
            $this->failedAt()?->relativeTo($root),
            $this->writtenAt()?->relativeTo($root),
            $this->duration,
        );
    }

    /**
     * Where the example is written: the line its it() is declared on.
     */
    public function writtenAt(): ?Location
    {
        return Location::at($this->file, $this->line);
    }

    /**
     * Where the example went wrong: its first failing expectation, or where its
     * error says to look from the file it is declared in; nowhere when it
     * passed or was left out.
     */
    public function failedAt(): ?Location
    {
        return match ($this->state()) {
            State::Failed => $this->firstFailingMatch()?->location(),
            State::Errored => $this->error?->brokeAt($this->file),
            default => null,
        };
    }

    /**
     * Checks whether any match result in this example is a failure.
     */
    public function isFailure(): bool
    {
        return $this->firstFailingMatch() !== null;
    }

    /**
     * Returns the message from the first failed match result, or empty string if none.
     */
    public function getFailureMessage(): string
    {
        return (string) $this->firstFailingMatch()?->getMessage();
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
     * Why the example was left pending or skipped, when it said.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Says the example ran to the end without making a single expectation:
     * it checked nothing, whatever the code did.
     */
    public function markRisky(): void
    {
        $this->risky = true;
    }

    public function isRisky(): bool
    {
        return $this->risky;
    }

    /**
     * Says the example was left pending because a sibling was focused with
     * fit() or fdescribe(), not because anyone deferred it.
     */
    public function markLeftOutByFocus(): void
    {
        $this->leftOutByFocus = true;
    }

    public function isLeftOutByFocus(): bool
    {
        return $this->leftOutByFocus;
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

    private function firstFailingMatch(): ?MatchResult
    {
        foreach ($this->matchResults as $matchResult) {
            if ($matchResult->isFailure()) {
                return $matchResult;
            }
        }

        return null;
    }
}
