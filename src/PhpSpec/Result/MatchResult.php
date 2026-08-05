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

use PhpSpec\CodeGeneration\SurroundingCode;
use PhpSpec\Results;

/**
 * @internal
 * Wraps the pass/fail outcome of a single expectation along with its detail.
 *
 * Provides factory methods for creating passed and failed results, and delegates
 * to the Detail for failure message, source location, and expected/actual values.
 */
final readonly class MatchResult implements Results
{
    /**
     * @param Result $result the pass/fail enum value
     * @param Detail $detail the detailed outcome (Successful or Failed)
     */
    public function __construct(private Result $result, private Detail $detail) {}

    /**
     * Returns an empty array since match results have no children.
     *
     * @return array<Results>
     */
    public function getResults(): array
    {
        return [];
    }

    /**
     * Creates a passing MatchResult with a Successful detail.
     */
    public static function passed(): MatchResult
    {
        return new self(Result::Passed, Detail\Successful::matcher());
    }

    /**
     * Creates a failing MatchResult with a Failed detail containing comparison data.
     *
     * @param mixed $expected the value the matcher expected
     * @param mixed $actual the value that was actually received
     * @param string $message human-readable failure description
     * @param string $file file path of the failing expectation
     * @param int $line line number of the failing expectation
     * @param ?string $fakeExpression optional expression for --fake code generation
     * @param ?string $matcher the matcher method that produced the failure (e.g. "toBe")
     * @param bool $negated whether the matcher was negated
     */
    public static function failed(
        mixed $expected,
        mixed $actual,
        string $message,
        string $file,
        int $line,
        ?string $fakeExpression = null,
        ?string $matcher = null,
        bool $negated = false,
        bool $impliedTarget = false,
    ): MatchResult {
        return new self(
            Result::Failed,
            Detail\Failed::matcher(
                $expected,
                $actual,
                $message,
                new SurroundingCode($file, $line),
                $line,
                $file,
                $fakeExpression,
                $matcher,
                $negated,
                $impliedTarget,
            ),
        );
    }

    /**
     * Returns the Result enum value (Passed or Failed).
     */
    public function getResult(): Result
    {
        return $this->result;
    }

    /**
     * Checks whether this match result represents a failure.
     */
    public function isFailure(): bool
    {
        return $this->result === Result::Failed;
    }

    /**
     * Returns the failure message, or the Nothing sentinel if passed.
     */
    public function getMessage(): mixed
    {
        return $this->detail->getFailure();
    }

    /**
     * Returns the surrounding source code lines at the failure site.
     *
     * @return array<int, string>
     */
    public function getCode(): array
    {
        return $this->detail->getSurroundingCode();
    }

    /**
     * Returns the line number of the expectation, or null for passed results.
     */
    public function getLine(): ?int
    {
        return $this->detail->getLine();
    }

    /**
     * Returns the file path of the expectation, or null for passed results.
     */
    public function getFile(): ?string
    {
        return $this->detail->getFile();
    }

    /**
     * Returns the expected value from a failed match, or null for passed results.
     */
    public function getExpected(): mixed
    {
        return $this->detail instanceof Detail\Failed ? $this->detail->getExpected() : null;
    }

    /**
     * Returns the actual value from a failed match, or null for passed results.
     */
    public function getActual(): mixed
    {
        return $this->detail instanceof Detail\Failed ? $this->detail->getActual() : null;
    }

    /**
     * Returns the fake expression for --fake code generation, or null if not applicable.
     */
    public function getFakeExpression(): ?string
    {
        return $this->detail instanceof Detail\Failed ? $this->detail->getFakeExpression() : null;
    }

    /**
     * Returns the matcher method that produced the failure (e.g. "toBe"), or null
     * for a passed result or when the matcher could not be determined.
     */
    public function getMatcher(): ?string
    {
        return $this->detail instanceof Detail\Failed ? $this->detail->getMatcher() : null;
    }

    /**
     * Returns whether the target was implied by the matcher name ("to be true")
     * rather than given by the caller; false for a passed result.
     */
    public function isTargetImplied(): bool
    {
        return $this->detail instanceof Detail\Failed && $this->detail->isTargetImplied();
    }

    /**
     * Returns whether the failing matcher was negated (expect(...)->not()->...);
     * false for a passed result.
     */
    public function isNegated(): bool
    {
        return $this->detail instanceof Detail\Failed && $this->detail->isNegated();
    }
}
