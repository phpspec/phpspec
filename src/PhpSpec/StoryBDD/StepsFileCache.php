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

namespace PhpSpec\StoryBDD;

use PhpSpec\FileLoadException;
use PhpSpec\Mock\ArrangingCode;
use Throwable;

/**
 * @internal
 * Requires each steps file once in a run and keeps the steps and hooks it
 * registered, so every feature path the run is given hands them to its own
 * fresh registry instead of requiring the file again. A steps file may
 * declare a function or a class at its top level, and requiring it a second
 * time redeclares it: a fatal no run can catch.
 */
final class StepsFileCache
{
    /** @var array<string, array{steps: StepRegistry, hooks: HookRegistry}> by real path */
    private array $definitions = [];

    /**
     * The steps and hooks a steps file registers, requiring it the first time
     * the run asks.
     *
     * @return array{steps: StepRegistry, hooks: HookRegistry}
     * @throws FileLoadException when the file does not parse or declares what cannot load
     */
    public function definitions(string $path): array
    {
        $realPath = realpath($path) ?: $path;
        ArrangingCode::loaded($realPath);

        return $this->definitions[$realPath] ??= $this->registeredBy($realPath);
    }

    /**
     * Requires the file into registries of its own, leaving the running ones
     * as they were.
     *
     * @return array{steps: StepRegistry, hooks: HookRegistry}
     */
    private function registeredBy(string $path): array
    {
        $running = StoryBDDRegistry::saveState();
        StoryBDDRegistry::init();

        try {
            require $path;

            return StoryBDDRegistry::saveState();
        } catch (Throwable $e) {
            throw new FileLoadException($path, $e);
        } finally {
            StoryBDDRegistry::restoreState($running);
        }
    }
}
