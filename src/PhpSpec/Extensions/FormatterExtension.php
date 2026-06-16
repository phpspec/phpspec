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

/**
 * Base class for formatter extensions. Subclasses must implement getName(),
 * formatPass(), and formatFail(). Other methods have sensible defaults.
 */
abstract class FormatterExtension
{
    /**
     * Returns the formatter name used with --format.
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Formats a passing example.
     *
     * @param string $title The example title
     *
     * @return string
     */
    abstract public function formatPass(string $title): string;

    /**
     * Formats a failing example.
     *
     * @param string $title   The example title
     * @param string $message The failure message
     *
     * @return string
     */
    abstract public function formatFail(string $title, string $message): string;

    /**
     * Formats a pending example.
     *
     * @param string $title The example title
     *
     * @return string
     */
    public function formatPending(string $title): string
    {
        return "PENDING: $title";
    }

    /**
     * Formats an errored example.
     *
     * @param string $title   The example title
     * @param string $message The error message
     *
     * @return string
     */
    public function formatError(string $title, string $message): string
    {
        return $this->formatFail($title, $message);
    }

    /**
     * Formats the final summary line shown after all examples have run.
     *
     * @param int $total   Total number of examples
     * @param int $passed  Number of passing examples
     * @param int $failed  Number of failing examples
     * @param int $pending Number of pending or skipped examples
     * @param int $errors  Number of errored examples
     *
     * @return string
     */
    public function formatSummary(int $total, int $passed, int $failed, int $pending, int $errors): string
    {
        return "$total examples ($passed passed, $failed failed, $pending pending, $errors errors)";
    }
}
