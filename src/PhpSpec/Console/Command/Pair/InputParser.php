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
 * Parses raw REPL input into a command name and argument string.
 */
final readonly class InputParser
{
    /**
     * Splits raw input into a command and its argument.
     *
     * @param string $input the raw user input
     * @return array{command: string, argument: string}
     */
    public function parse(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return ['command' => '', 'argument' => ''];
        }

        $parts = preg_split('/\s+/', $input);
        $command = strtolower($parts[0]);

        // Collect all non-option tokens after the command
        $args = [];
        for ($i = 1, $count = count($parts); $i < $count; $i++) {
            if (!str_starts_with($parts[$i], '-')) {
                $args[] = $parts[$i];
            }
        }

        return ['command' => $command, 'argument' => implode(' ', $args)];
    }
}
