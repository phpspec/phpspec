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

/**
 * @internal
 * Which files guard has an opinion about.
 *
 * The exemptions live here rather than in the rule, so the rule stays one
 * question about lines and coverage. Specs and features are never guarded:
 * they are the statement of intent, and demanding that the intent be covered
 * by itself would invert the whole idea. Everything else is bounded by the
 * configured roots, minus whatever the project has said to allow.
 */
final readonly class Scope
{
    /**
     * @param list<string> $paths guarded roots
     * @param list<string> $allow globs dropped from the delta
     * @param list<string> $exempt roots never guarded, whatever the configuration says
     */
    public function __construct(
        private array $paths,
        private array $allow = [],
        private array $exempt = ['spec', 'features'],
    ) {}

    /**
     * Whether guard judges this file at all.
     */
    public function admits(string $file): bool
    {
        $file = ltrim(str_replace('\\', '/', $file), './');

        if (!str_ends_with($file, '.php')) {
            return false;
        }

        foreach ($this->exempt as $root) {
            if ($this->under($file, $root)) {
                return false;
            }
        }

        foreach ($this->allow as $glob) {
            if (fnmatch(ltrim($glob, './'), $file)) {
                return false;
            }
        }

        foreach ($this->paths as $root) {
            if ($this->under($file, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The delta with everything guard has no opinion about removed.
     */
    public function bound(Delta $delta): Delta
    {
        $kept = [];
        foreach ($delta->all() as $file => $lines) {
            if ($this->admits($file)) {
                $kept[$file] = $lines;
            }
        }

        return Delta::of($kept);
    }

    /**
     * The guarded roots, as the project wrote them.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        return $this->paths;
    }

    private function under(string $file, string $root): bool
    {
        $root = rtrim(ltrim(str_replace('\\', '/', $root), './'), '/');

        return $root === '' || $file === $root || str_starts_with($file, $root . '/');
    }
}
