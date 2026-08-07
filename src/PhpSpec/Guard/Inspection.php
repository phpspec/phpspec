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

namespace PhpSpec\Guard;

use PhpSpec\Configuration;
use PhpSpec\Filesystem;

/**
 * @internal
 * Guard as a run meets it: the configuration made into the collaborators that
 * answer it, and one question to ask them.
 *
 * Assembled here rather than in the command, so the command decides whether to
 * judge and this decides what judging means.
 */
final readonly class Inspection
{
    public function __construct(
        private Baseline $baseline,
        private Changes $changes,
        private Guard $guard,
    ) {}

    /**
     * The inspection this project asks for, or null when guard is off, which
     * is the default and the only safe one: guard fails runs, and nobody opts
     * into that by omission.
     */
    public static function of(Configuration $config, Filesystem $filesystem, string $baseDir = '.'): ?self
    {
        $guard = $config->getGuardConfig();
        if ($guard === null || $guard['status'] !== 'active') {
            return null;
        }

        return self::from($guard, $filesystem, $baseDir);
    }

    /**
     * The same inspection, for a check somebody asked for outright. Whether
     * guard is on day to day is beside the point when the question has been put
     * directly, which is how CI and a pre-commit hook ask it.
     */
    public static function forChecking(Configuration $config, Filesystem $filesystem, string $baseDir = '.'): self
    {
        $guard = $config->getGuardConfig() ?? [];

        return self::from($guard + [
            'paths' => ['src'],
            'allow' => [],
            'detection' => 'git',
        ], $filesystem, $baseDir);
    }

    /**
     * @param array{paths: list<string>, allow: list<string>, detection: string, ...} $guard
     */
    private static function from(array $guard, Filesystem $filesystem, string $baseDir): self
    {
        $git = new ShellGit($baseDir);
        $scope = new Scope($guard['paths'], $guard['allow']);

        // Which reader answers is decided by what the baseline recorded, not by
        // what the configuration prefers: the preference was already honoured
        // when the baseline was written, and a baseline read by the wrong
        // reader produces a verdict about the whole project rather than about
        // this session.
        $changes = new RecordedChanges(
            new GitDelta($git, $filesystem, $scope, $baseDir),
            new SnapshotDelta($filesystem, $scope, $baseDir),
        );

        return new self(
            new Baseline($filesystem, $git, $baseDir),
            $changes,
            new Guard($filesystem, $baseDir),
        );
    }

    /**
     * What guard makes of this run.
     *
     * A checkout where guard is on but nothing was ever recorded has nothing to
     * compare against. That is not a clean bill of health: it is the state
     * every fresh clone is in, because the baseline is local by design, and a
     * silent pass there leaves a whole team believing they are guarded.
     */
    public function judge(Coverage $coverage): Verdict
    {
        try {
            return $this->verdict($this->baseline->recorded(), $coverage);
        } catch (CannotJudge $cannot) {
            return Verdict::cannotJudge($cannot->getMessage());
        }
    }

    /**
     * The same judgement against a baseline named on the command line, for CI
     * and pre-commit, where the point of comparison is the branch this work
     * came from rather than where the session started.
     */
    public function judgeAgainst(string $commit, Coverage $coverage): Verdict
    {
        try {
            return $this->verdict(['kind' => 'commit', 'commit' => $commit], $coverage);
        } catch (CannotJudge $cannot) {
            return Verdict::cannotJudge($cannot->getMessage());
        }
    }

    /**
     * @param array{kind: string, commit?: string, files?: array<string, string>} $recorded
     * @throws CannotJudge when there is no evidence to judge on
     */
    private function verdict(array $recorded, Coverage $coverage): Verdict
    {
        // Coverage of nothing is a collection that failed, not a codebase
        // without a single example: judging on it would condemn every line of
        // the change at once.
        if ($coverage->isEmpty()) {
            throw new CannotJudge('Guard cannot judge this run: no coverage was collected.');
        }

        return Verdict::of($this->guard->violations($this->changes->since($recorded), $coverage));
    }
}
