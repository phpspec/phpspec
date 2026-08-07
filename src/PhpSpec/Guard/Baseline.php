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
 * Where this session started, so guard can tell what it changed since. In a
 * repository that is a commit; without one it is a snapshot of the source
 * files, each with the hash of what it held. Kept under .phpspec/guard/ so it
 * survives between commands but never enters the project's history.
 */
final readonly class Baseline
{
    private const FILE = '.phpspec/guard/baseline.json';

    public function __construct(
        private Filesystem $filesystem,
        private Git $git,
        private string $baseDir = '.',
    ) {}

    /**
     * Records where the session starts, and returns what was recorded. In a
     * repository this is the commit, because committing is a deliberate act of
     * acceptance; otherwise it is what every guarded file holds right now.
     *
     * A project that asked for mtime detection gets a snapshot even where git
     * could have answered: it said which reader it wants, and recording the
     * other one would hand that reader a baseline it cannot understand.
     *
     * @param list<string> $paths the guarded roots, used when there is no repository
     * @param string $detection which reader the project asked for
     * @return array{kind: string, commit?: string, files?: array<string, string>}
     */
    public function record(array $paths, string $detection = 'git'): array
    {
        $commit = $detection === 'git' && $this->git->isRepository() ? $this->git->head() : null;

        $recorded = $commit !== null
            ? ['kind' => 'commit', 'commit' => $commit]
            : ['kind' => 'snapshot', 'files' => $this->snapshot($paths)];

        $this->write($recorded);

        return $recorded;
    }

    /**
     * What was recorded last.
     *
     * There being nothing to read is the ordinary state of a fresh clone,
     * because the baseline is local by design, and it is still a reason guard
     * cannot judge rather than a reason to say everything is fine.
     *
     * @return array{kind: string, commit?: string, files?: array<string, string>}
     * @throws CannotJudge when nothing was recorded here, or what was recorded cannot be read
     */
    public function recorded(): array
    {
        $path = $this->path();
        if (!$this->filesystem->exists($path)) {
            throw new CannotJudge('Guard is on but no baseline is recorded in this checkout: run "bin/phpspec guard" to judge from here.');
        }

        $document = json_decode($this->filesystem->read($path), true);
        $recorded = is_array($document) && isset($document['recorded']) ? $document['recorded'] : null;

        if (!is_array($recorded) || !isset($recorded['kind'])) {
            throw new CannotJudge(sprintf('Guard cannot read the baseline in %s: run "bin/phpspec guard" to record it again.', self::FILE));
        }

        return $recorded;
    }

    /**
     * Every guarded file with what it holds, so a later run can say not just
     * which files changed but which lines. A hash would be smaller and would
     * only ever support a file-level verdict, which would implicate every
     * untested line in a legacy file the moment somebody touched one of them.
     * The cost is a local cache under .phpspec/, which is the right place to
     * spend it.
     *
     * @param list<string> $paths
     * @return array<string, string>
     */
    private function snapshot(array $paths): array
    {
        $files = [];

        foreach ($paths as $root) {
            $this->collect(rtrim($this->baseDir, '/') . '/' . ltrim($root, './'), $files);
        }

        ksort($files);

        return $files;
    }

    /**
     * Walks a guarded root, hashing every PHP file under it.
     *
     * @param array<string, string> $files
     */
    private function collect(string $dir, array &$files): void
    {
        if (!$this->filesystem->isDir($dir)) {
            return;
        }

        foreach ($this->filesystem->scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if ($this->filesystem->isDir($path)) {
                $this->collect($path, $files);
            } elseif (str_ends_with($entry, '.php')) {
                $files[$this->relative($path)] = $this->filesystem->read($path);
            }
        }
    }

    /**
     * @param array{kind: string, commit?: string, files?: array<string, string>} $recorded
     */
    private function write(array $recorded): void
    {
        $path = $this->path();
        $dir = dirname($path);

        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $this->filesystem->write($path, (string) json_encode(
            ['recorded' => $recorded],
            JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ));
    }

    private function path(): string
    {
        return rtrim($this->baseDir, '/') . '/' . self::FILE;
    }

    /**
     * A path as the project refers to it, so a baseline recorded from one
     * working directory still reads in another.
     */
    private function relative(string $file): string
    {
        $prefix = rtrim($this->baseDir, '/') . '/';

        return str_starts_with($file, $prefix) ? substr($file, strlen($prefix)) : $file;
    }
}
