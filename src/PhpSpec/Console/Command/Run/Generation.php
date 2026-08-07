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

namespace PhpSpec\Console\Command\Run;

/**
 * @internal
 * How an offer to write code is answered.
 *
 * Three answers, because "there is nobody to ask" was previously read as
 * "yes": a run with no terminal to prompt would write classes into a source
 * tree nobody had agreed to. An unanswered question is not consent.
 */
enum Generation
{
    /** Put every offer to the person running it. */
    case Asks;

    /** Take every offer, because the person already said so (accept, pair). */
    case Accepts;

    /** Write nothing, because there is nobody to ask. */
    case Declines;
}
