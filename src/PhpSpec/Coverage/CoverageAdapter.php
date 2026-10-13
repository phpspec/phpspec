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

namespace PhpSpec\Coverage;

use PhpSpec\Coverage\Driver\PcovDriver;
use PhpSpec\Coverage\Driver\XdebugDriver;

/**
 * @internal
 * The engines that count the lines a run executes: PCOV, or Xdebug in
 * coverage mode. A project names one in its config; a run with none named
 * takes whichever is enabled.
 */
enum CoverageAdapter: string
{
    case Pcov = 'pcov';
    case Xdebug = 'xdebug';

    public function driver(): CoverageDriver
    {
        return match ($this) {
            self::Pcov => new PcovDriver(),
            self::Xdebug => new XdebugDriver(),
        };
    }

    /**
     * The ini settings that make a child process collect with this engine,
     * the other one switched off.
     *
     * @return list<string>
     */
    public function ini(): array
    {
        return match ($this) {
            self::Pcov => ['pcov.enabled=1', 'xdebug.mode=off'],
            self::Xdebug => ['xdebug.mode=coverage', 'pcov.enabled=0'],
        };
    }

    /**
     * Why a run that wanted coverage could not collect with this engine.
     */
    public function refusal(): string
    {
        return match ($this) {
            self::Pcov => 'Code coverage requires PCOV enabled',
            self::Xdebug => 'Code coverage requires Xdebug with coverage mode enabled',
        };
    }

    /**
     * The same command, run with this engine collecting.
     */
    public function remedy(string $invocation): string
    {
        return match ($this) {
            self::Pcov => 'php -d pcov.enabled=1 ' . $invocation,
            self::Xdebug => 'XDEBUG_MODE=coverage ' . $invocation,
        };
    }
}
