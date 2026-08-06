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
        private Filesystem $filesystem,
        private string $baseDir = '.',
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

        $git = new ShellGit($baseDir);
        $scope = new Scope($guard['paths'], $guard['allow']);

        $changes = $guard['detection'] === 'git' && $git->isRepository()
            ? new GitDelta($git, $filesystem, $scope, $baseDir)
            : new SnapshotDelta($filesystem, $scope, $baseDir);

        return new self(
            new Baseline($filesystem, $git, $baseDir),
            $changes,
            new Guard($filesystem, $baseDir),
            $filesystem,
            $baseDir,
        );
    }

    /**
     * What guard makes of this run. A project where guard was turned on but no
     * baseline was ever recorded has nothing to compare against, and says so by
     * holding rather than by inventing a verdict.
     */
    public function judge(Coverage $coverage): Verdict
    {
        $recorded = $this->baseline->recorded();
        if ($recorded === null) {
            return Verdict::clean();
        }

        return new Verdict(
            $this->guard->violations($this->changes->since($recorded), $coverage),
            $this->filesystem,
            $this->baseDir,
        );
    }

    /**
     * The same judgement against a baseline named on the command line, for CI
     * and pre-commit, where the point of comparison is the branch this work
     * came from rather than where the session started.
     */
    public function judgeAgainst(string $commit, Coverage $coverage): Verdict
    {
        return new Verdict(
            $this->guard->violations($this->changes->since(['kind' => 'commit', 'commit' => $commit]), $coverage),
            $this->filesystem,
            $this->baseDir,
        );
    }
}
