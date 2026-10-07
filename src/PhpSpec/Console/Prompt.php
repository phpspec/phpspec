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

/**
 * @internal
 * A line typed in answer to a question, read from the standard input. An
 * empty answer is somebody pressing Enter; nothing to read at all is nobody
 * there, and comes back as null.
 */
class Prompt
{
    public function ask(string $prompt): ?string
    {
        if (function_exists('readline') && stream_isatty(STDIN)) {
            $answer = readline($prompt);

            return $answer === false ? null : $answer;
        }

        $line = fgets(STDIN);

        return $line === false ? null : rtrim($line, "\r\n");
    }
}
