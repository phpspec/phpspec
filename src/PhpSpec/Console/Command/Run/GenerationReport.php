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

namespace PhpSpec\Console\Command\Run;

/**
 * @internal
 * The channel by which pair mode collects a run's code-generation candidates.
 *
 * Pair mode runs each suite in a fresh subprocess so newly generated code is
 * actually loaded (PHP cannot redefine a class already loaded in the REPL).
 * That subprocess needs to report what could be generated. Pair mode tells its
 * own child where to write the report through an environment variable it sets
 * only on that child — an explicit parent-to-child handoff, not a user-facing
 * flag and not an ambient file every run would look for. A plain `phpspec run`
 * never has the variable set, so it never touches this machinery.
 */
final class GenerationReport
{
    /**
     * Environment variable naming the file a run should write its candidates
     * to. Set by pair mode on the run subprocess it spawns.
     */
    public const ENV_VAR = 'PHPSPEC_PAIR_REPORT';

    /**
     * Returns the file the current run was asked to report to, or null when it
     * was not asked (a normal run).
     *
     * @return string|null the report path, or null
     */
    public static function requestedPath(): ?string
    {
        $path = getenv(self::ENV_VAR);

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Writes a run's outcome (candidates + suite summary) to the report file as
     * an envelope: {"candidates": {...}|null, "suite": {...}|null}.
     *
     * @param string $path the report file path
     * @param RunOutcome $outcome the outcome to report
     * @return void
     */
    public static function write(string $path, RunOutcome $outcome): void
    {
        file_put_contents($path, (string) json_encode([
            'candidates' => $outcome->candidates?->toArray(),
            'suite' => $outcome->summary?->toArray(),
        ]));
    }

    /**
     * Reads the outcome a run reported, or null when the file is missing or empty.
     *
     * Accepts both the current envelope shape and a legacy report that held the
     * bare candidates array (in which case the outcome carries no suite summary).
     *
     * @param string $path the report file path
     * @return RunOutcome|null the reported outcome, or null
     */
    public static function read(string $path): ?RunOutcome
    {
        if (!is_file($path)) {
            return null;
        }

        $json = file_get_contents($path);
        $data = $json !== false && $json !== '' ? json_decode($json, true) : null;

        if (!is_array($data)) {
            return null;
        }

        $isEnvelope = array_key_exists('candidates', $data) || array_key_exists('suite', $data);

        if (!$isEnvelope) {
            // Legacy report: the whole array was the candidates.
            return new RunOutcome(GenerationCandidates::fromArray($data), null);
        }

        $candidates = is_array($data['candidates'] ?? null) ? GenerationCandidates::fromArray($data['candidates']) : null;
        $summary = is_array($data['suite'] ?? null) ? SuiteSummary::fromArray($data['suite']) : null;

        return new RunOutcome($candidates, $summary);
    }
}
