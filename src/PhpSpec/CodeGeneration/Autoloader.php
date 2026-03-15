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

namespace PhpSpec\CodeGeneration;

/**
 * Interactive PSR-4 autoloader that prompts the user to generate missing classes.
 * Registered via spl_autoload_register to intercept class-not-found errors during spec runs.
 */
final class Autoloader
{
    /**
     * Attempts to autoload a class by prompting the user to generate it interactively.
     * Only prompts when STDIN is a TTY; silently returns otherwise.
     *
     * @param string $class the fully qualified class name to autoload
     * @return void
     */
    public static function autoload(string $class): void
    {
        if (!posix_isatty(STDIN)) {
            return;
        }

        echo "\n";

        echo "Looks like you are trying to spec $class, a class that doesn't exist yet.\n";
        echo "Would you like me to generate that class for you? [y/n]\n";
        $answer = readline();
        if ($answer === 'y') {
            $generator = new ClassGenerator();
            $generator->generate($class);
            require_once(getcwd() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR .
                         str_replace('\\', DIRECTORY_SEPARATOR, $class) .
                         '.php');
        }
    }
}
