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

use PhpSpec\Filesystem;

/**
 * @internal
 * What changed, as git sees it: the working tree against the baseline commit,
 * plus the whole of any file git has never been told about.
 *
 * The working tree rather than HEAD, because during a live cycle nothing is
 * committed yet and the uncommitted part is precisely what guard is asked
 * about. A plain `git diff <ref>` already compares the tree to the ref, so
 * staged and unstaged edits arrive together.
 */
final readonly class GitDelta implements Changes
{
    public function __construct(
        private Git $git,
        private Filesystem $filesystem,
        private Scope $scope,
        private string $baseDir = '.',
    ) {}

    public function since(array $baseline): Delta
    {
        $commit = $baseline['commit'] ?? null;
        if (!is_string($commit) || $commit === '') {
            return Delta::nothing();
        }

        $roots = $this->scope->roots();
        $changed = UnifiedDiff::added($this->git->diff($commit, $roots));

        foreach ($this->git->untracked($roots) as $file) {
            // A file git has never seen is new from its first line to its last.
            $changed[$file] = $this->everyLine($file);
        }

        return $this->scope->bound(Delta::of($changed));
    }

    /**
     * @return list<int>
     */
    private function everyLine(string $file): array
    {
        $path = rtrim($this->baseDir, '/') . '/' . ltrim($file, './');
        if (!$this->filesystem->exists($path)) {
            return [];
        }

        return range(1, max(1, count($this->filesystem->readLines($path))));
    }
}
