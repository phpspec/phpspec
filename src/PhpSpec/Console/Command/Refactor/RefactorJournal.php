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

namespace PhpSpec\Console\Command\Refactor;

use PhpSpec\Filesystem;

/**
 * @internal
 * Append-only memory of the applied refactorings (.phpspec/ai/journal.jsonl),
 * one JSON line each. Later runs read it so the model never undoes or redoes
 * a recent refactoring, and `next` can steer to growth when nothing changed
 * since the last one.
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
     * The timestamp of the most recent refactoring, or null for none yet.
     */
    public function lastRefactoringAt(): ?int
    {
        $entries = $this->entries();
        $last = end($entries);

        return $last === false ? null : (int) $last['at'];
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

    private function path(): string
    {
        return (getcwd() ?: '.') . '/' . self::PATH;
    }
}
