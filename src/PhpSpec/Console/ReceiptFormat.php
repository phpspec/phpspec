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

namespace PhpSpec\Console;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface as Input;

/**
 * @internal
 * How a command answers: in prose for a person, or as one JSON receipt for a
 * coding agent.
 */
enum ReceiptFormat: string
{
    case Pretty = 'pretty';
    case Agent = 'agent';

    /**
     * The format the command was asked for.
     *
     * @throws InvalidArgumentException when it speaks no such format
     */
    public static function of(Input $input): self
    {
        $format = (string) $input->getOption('format');

        return self::tryFrom($format) ?? throw new InvalidArgumentException(sprintf('Unknown format: %s (available: pretty, agent)', $format));
    }
}
