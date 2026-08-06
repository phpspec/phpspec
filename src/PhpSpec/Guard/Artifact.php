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
 * Coverage as a previous run left it on disk, for the check that runs after the
 * suite rather than inside it: CI runs the specs once, keeps the artifact, and
 * asks guard about it.
 */
final readonly class Artifact
{
    public function __construct(private Filesystem $filesystem) {}

    /**
     * Reads a --coverage-json report into the same answer the live run gives,
     * or says why it could not.
     *
     * @return Coverage|string the coverage, or what was wrong with the file
     */
    public function read(string $path): Coverage|string
    {
        if (!$this->filesystem->exists($path)) {
            return sprintf('Coverage file not found: %s.', $path);
        }

        $report = json_decode($this->filesystem->read($path), true);
        if (!is_array($report) || !isset($report['sources']) || !is_array($report['sources'])) {
            return sprintf('%s is not a PhpSpec coverage report: run --coverage-json to make one.', $path);
        }

        $hits = [];
        foreach ($report['sources'] as $file => $source) {
            // "lines" holds what ran, "executable" what there was to run: a
            // line in neither was never code, and guard must not call it
            // untested logic.
            foreach ($source['executable'] ?? array_keys($source['lines'] ?? []) as $line) {
                $hits[$file][(int) $line] = -1;
            }

            foreach (array_keys($source['lines'] ?? []) as $line) {
                $hits[$file][(int) $line] = 1;
            }
        }

        return new Coverage($hits);
    }
}
