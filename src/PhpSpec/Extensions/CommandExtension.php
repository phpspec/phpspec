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

namespace PhpSpec\Extensions;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for command extensions. Subclasses must implement getName(),
 * getDescription(), and execute().
 */
abstract class CommandExtension
{
    /**
     * Returns the command name (e.g. 'lint').
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Returns a short description of the command.
     *
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Executes the command.
     *
     * @param InputInterface  $input  The console input
     * @param OutputInterface $output The console output
     *
     * @return int Exit code (0 = success)
     */
    abstract public function execute(InputInterface $input, OutputInterface $output): int;
}
