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

namespace PhpSpec\Ai;

use PhpSpec\Filesystem;

/**
 * @internal
 * Append-only memory of the applied refactorings (.phpspec/ai/journal.jsonl),
 * one JSON line each. Later runs read it so the model reverses a recent
 * refactoring only with a stated rationale, and `next` grounds itself in
 * which classes are already polished and unchanged since.
 */
final class RefactorJournal
{
    private const PATH = '.phpspec/ai/journal.jsonl';

    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * Appends one applied refactoring to the journal.
     */
    public function record(string $target, string $technique, string $description): void
    {
        $entry = json_encode([
            'at' => time(),
            'command' => 'refactor',
            'target' => $target,
            'technique' => $technique,
            'description' => $description,
        ]) . "\n";

        $path = $this->path();
        $dir = dirname($path);
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $existing = $this->filesystem->exists($path) ? $this->filesystem->read($path) : '';
        $this->filesystem->write($path, $existing . $entry);
    }

    /**
     * The recent entries rendered for a prompt, newest last; empty when the
     * journal holds nothing.
     */
    public function rendered(int $limit = 5): string
    {
        $lines = [];
        foreach (array_slice($this->entries(), -$limit) as $entry) {
            $lines[] = sprintf('- %s on %s: %s', $entry['technique'], $entry['target'], $entry['description']);
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{at: int, target: string, technique: string, description: string}>
     */
    private function entries(): array
    {
        $path = $this->path();
        if (!$this->filesystem->exists($path)) {
            return [];
        }

        $entries = [];
        foreach (explode("\n", trim($this->filesystem->read($path))) as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry) && isset($entry['at'], $entry['target'], $entry['technique'], $entry['description'])) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * The journalled classes whose source has not changed since their latest
     * refactoring: polishing is done there, so the next step is growth. A
     * class modified after (or gone) is fair game for refactoring again.
     *
     * @param string $srcDir absolute path of the source root the targets live under
     * @return list<string>
     */
    public function unchangedTargets(string $srcDir): array
    {
        $latest = [];
        foreach ($this->entries() as $entry) {
            $target = (string) $entry['target'];
            $latest[$target] = max((int) $entry['at'], $latest[$target] ?? 0);
        }

        $unchanged = [];
        foreach ($latest as $target => $at) {
            $file = $srcDir . '/' . str_replace('\\', '/', $target) . '.php';
            if ($this->filesystem->exists($file) && $this->filesystem->mtime($file) <= $at) {
                $unchanged[] = $target;
            }
        }

        return $unchanged;
    }

    private function path(): string
    {
        return (getcwd() ?: '.') . '/' . self::PATH;
    }
}
