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

namespace PhpSpec\Specification;

use PhpSpec\EventDispatcher\DispatcherRegistry;

/**
 * @internal
 * Parses each spec file at most once per process and keeps its top-level blocks
 * as pristine templates. Every run then rebuilds fresh, world-bound blocks from
 * the templates (see Specification::loadSubject) instead of requiring the file
 * again.
 *
 * Re-requiring matters because a spec file may declare a class/interface/trait
 * at its top level: running the file a second time redeclares it and PHP raises
 * an uncatchable "cannot declare ... already in use" fatal. A long-lived process
 * such as `phpspec pair` runs the same suite repeatedly, so requiring once and
 * rebinding is what keeps repeated runs both correct and crash-free.
 *
 * The cache is keyed by the file's path and content signature (mtime + size),
 * so editing a spec — e.g. `exemplify` appending an example — re-parses it while
 * unchanged files stay cached.
 */
final class SpecFileCache
{
    /** @var array<string, array{signature: string, templates: list<SpecBlock>}> */
    private static array $entries = [];

    /**
     * Returns the top-level template blocks for a spec file, parsing it once
     * per content signature and reusing the result on later calls.
     *
     * The key is the file's real path so the same file requested by different
     * path strings (a scanned "./spec/X" and an explicit "spec/X") resolves to
     * a single entry — otherwise the file would be require'd twice and a
     * top-level type declaration would fatal on the second require.
     *
     * There is deliberately no way to clear the cache: a spec file is require'd
     * at most once per process, so eviction could only reintroduce that
     * redeclaration fatal on a later run.
     *
     * @param string $path filesystem path to the .spec.php file
     * @return list<SpecBlock> pristine top-level blocks
     */
    public static function templates(string $path): array
    {
        $realPath = realpath($path) ?: $path;
        $signature = self::signature($realPath);
        $entry = self::$entries[$realPath] ?? null;

        if ($entry === null || $entry['signature'] !== $signature) {
            $entry = ['signature' => $signature, 'templates' => self::parse($realPath)];
            self::$entries[$realPath] = $entry;
        }

        return $entry['templates'];
    }

    /**
     * Computes a content signature that changes when the file is edited.
     *
     * @param string $path filesystem path to the .spec.php file
     * @return string the signature
     */
    private static function signature(string $path): string
    {
        // PHP caches stat results per path for the whole process, so an edit
        // made during a long-lived `phpspec pair` session would otherwise read
        // a stale mtime/size and never invalidate the cache.
        clearstatcache(true, $path);

        return @filemtime($path) . ':' . @filesize($path);
    }

    /**
     * Loads a spec file once, collecting the top-level blocks its describe()/
     * it() calls register. The blocks stay pristine (never run); each run copies
     * them via withWorld().
     *
     * @param string $path filesystem path to the .spec.php file
     * @return list<SpecBlock> the collected top-level blocks
     */
    private static function parse(string $path): array
    {
        $collector = new BlockCollector();
        $dispatcher = DispatcherRegistry::dispatcher();
        $dispatcher->pushScope($collector);

        try {
            (new Subject())->load($path);
        } finally {
            $dispatcher->popScope();
        }

        return $collector->blocks();
    }
}
