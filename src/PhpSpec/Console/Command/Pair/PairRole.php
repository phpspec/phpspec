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

namespace PhpSpec\Console\Command\Pair;

/**
 * @internal
 * Who holds the keyboard in a pairing session. Driving means deciding what gets
 * generated and pulling the trigger; the code generators are the shared
 * keyboard. Swapping changes who decides — not whether generation is available.
 */
enum PairRole
{
    /** The human drives (uses commands/choosers); the AI navigates and never writes on its own. */
    case HumanDrives;

    /** The AI drives (one artifact per turn); the human navigates at the intent level. */
    case AiDrives;

    /**
     * Whether the AI is navigating this turn (reviewing and suggesting, not writing).
     */
    public function aiIsNavigator(): bool
    {
        return $this === self::HumanDrives;
    }

    /**
     * Whether the AI is driving this turn (deciding and generating one artifact).
     */
    public function aiIsDriver(): bool
    {
        return $this === self::AiDrives;
    }

    /**
     * The prompt artifact (navigator.txt / driver.txt) that encodes this role's contract.
     *
     * @return 'navigator'|'driver'
     */
    public function promptArtifact(): string
    {
        return $this === self::HumanDrives ? 'navigator' : 'driver';
    }

    /**
     * The AI command this role runs as: its manifest (`commands/<name>.txt`)
     * declares the role's tool surface and model params.
     *
     * @return 'navigator'|'driver'
     */
    public function commandName(): string
    {
        return $this->promptArtifact();
    }

    /**
     * The role after a /swap.
     */
    public function swapped(): self
    {
        return $this === self::HumanDrives ? self::AiDrives : self::HumanDrives;
    }

    /**
     * The one-line contract announced when entering this role.
     */
    public function contractLine(): string
    {
        return $this === self::AiDrives
            ? "I'm driving — you navigate. Tell me the intent; I'll make it real one step at a time."
            : "You're driving — I'll navigate. Ask for the detail when you want it.";
    }
}
