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

namespace PhpSpec\Result\Detail;

use PhpSpec\CodeGeneration\SurroundingCode;
use PhpSpec\Result\Detail;

/**
 * @internal
 * Carries detailed information about a failed match, including expected/actual values,
 * surrounding source code, and the optional fake expression for --fake code generation.
 */
final class Failed extends Detail
{
    /** @var mixed The expected value from the matcher */
    private mixed $expected;

    /** @var mixed The actual value that was compared */
    private mixed $actual;

    /** @var SurroundingCode Source code surrounding the failure site */
    private SurroundingCode $code;

    /** @var int Line number of the failing expectation */
    private int $line;

    /** @var string File path of the failing expectation */
    private string $file;

    /** @var string|null Expression used for --fake code generation */
    private ?string $fakeExpression = null;

    /** @var string|null The matcher method that produced the failure (e.g. "toBe") */
    private ?string $matcher = null;

    /** @var bool Whether the matcher was negated (expect(...)->not()->...) */
    private bool $negated = false;

    /** @var string|null The matcher's relation phrase for the expected/actual pair (e.g. "to be contained in") */
    private ?string $relation = null;

    /**
     * Creates a Failed detail from a matcher comparison.
     *
     * @param mixed $expected the value the matcher expected
     * @param mixed $actual the value that was actually received
     * @param string $message human-readable failure description
     * @param SurroundingCode $code source code surrounding the failure site
     * @param int $line line number of the failing expectation
     * @param string $file file path of the failing expectation
     * @param ?string $fakeExpression optional expression for --fake code generation
     * @param ?string $matcher the matcher method that produced the failure (e.g. "toBe")
     * @param bool $negated whether the matcher was negated
     * @param ?string $relation the matcher's relation phrase for the expected/actual pair
     */
    public static function matcher(mixed $expected, mixed $actual, string $message, SurroundingCode $code, int $line, string $file, ?string $fakeExpression = null, ?string $matcher = null, bool $negated = false, ?string $relation = null): Detail
    {
        $failed = new self($message);
        $failed->expected = $expected;
        $failed->actual = $actual;
        $failed->code = $code;
        $failed->line = $line;
        $failed->file = $file;
        $failed->fakeExpression = $fakeExpression;
        $failed->matcher = $matcher;
        $failed->negated = $negated;
        $failed->relation = $relation;
        return $failed;
    }

    /**
     * Returns the matcher method that produced the failure, or null when unknown.
     */
    public function getMatcher(): ?string
    {
        return $this->matcher;
    }

    /**
     * Returns whether the matcher was negated (expect(...)->not()->...).
     */
    public function isNegated(): bool
    {
        return $this->negated;
    }

    /**
     * Returns the matcher's relation phrase for the expected/actual pair, or
     * null when the matcher declares none.
     */
    public function getRelation(): ?string
    {
        return $this->relation;
    }

    /**
     * Returns the expression for --fake code generation, or null if not set.
     */
    public function getFakeExpression(): ?string
    {
        return $this->fakeExpression;
    }

    /**
     * Returns the expected value from the matcher comparison.
     */
    public function getExpected(): mixed
    {
        return $this->expected;
    }

    /**
     * Returns the actual value that was compared.
     */
    public function getActual(): mixed
    {
        return $this->actual;
    }

    /**
     * Returns the source code lines surrounding the failure site.
     *
     * @return array<int, string>
     */
    public function getSurroundingCode(): array
    {
        return $this->code->toArray();
    }

    /**
     * Returns the line number of the failing expectation.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Returns the file path of the failing expectation.
     */
    public function getFile(): string
    {
        return $this->file;
    }
}
