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
     * @param list<string> $paths the guarded roots, used when there is no repository
     * @return array{kind: string, commit?: string, files?: array<string, string>}
     */
    public function record(array $paths): array
    {
        $commit = $this->git->isRepository() ? $this->git->head() : null;

        $recorded = $commit !== null
            ? ['kind' => 'commit', 'commit' => $commit]
            : ['kind' => 'snapshot', 'files' => $this->snapshot($paths)];

        $this->write($recorded);

        return $recorded;
    }

    /**
     * What was recorded last, or null when guard has never been turned on here.
     *
     * @return array{kind: string, commit?: string, files?: array<string, string>}|null
     */
    public function recorded(): ?array
    {
        $path = $this->path();
        if (!$this->filesystem->exists($path)) {
            return null;
        }

        $document = json_decode($this->filesystem->read($path), true);
        $recorded = is_array($document) && isset($document['recorded']) ? $document['recorded'] : null;

        return is_array($recorded) && isset($recorded['kind']) ? $recorded : null;
    }

    /**
     * Every guarded file with the hash of what it holds, so a later run can
     * tell which of them changed without asking git.
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
                $files[$this->relative($path)] = md5($this->filesystem->read($path));
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
