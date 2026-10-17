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
 * What a key pressed in the pair input means to the line being typed.
 */
enum KeyName
{
    case Text;
    case Enter;
    case NewLine;
    case Left;
    case Right;
    case Up;
    case Down;
    case WordLeft;
    case WordRight;
    case Home;
    case End;
    case Backspace;
    case Delete;
    case Tab;
    case Cancel;
    case EndOfInput;
    case Ignored;
}
