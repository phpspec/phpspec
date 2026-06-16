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
 * Thin readline loop for the pair programming REPL.
 * All logic is delegated to CommandDispatcher.
 */
final readonly class Repl
{
    /**
     * @param CommandDispatcher $dispatcher the command dispatcher that handles parsed input
     * @param PairOutput $output the pair-mode output helper for terminal layout management
     */
    /**
     * @param bool $aiAvailable whether an AI provider is configured
     */
    public function __construct(
        private CommandDispatcher $dispatcher,
        private PairOutput $output,
        private bool $aiAvailable = false,
    ) {}

    /**
     * Runs the REPL loop: setup layout, read, dispatch, repeat until quit or EOF.
     *
     * @return int exit code (0 = normal exit, 1 = not a TTY)
     */
    public function run(): int
    {
        $this->output->setupLayout($this->aiAvailable);

        while (true) {
            $this->output->prepareForInput();
            $line = readline('> ');

            if ($line === false) {
                break;
            }

            $this->output->returnToContent();

            $line = trim($line);
            if ($line !== '') {
                readline_add_history($line);
                $this->output->echoInput($line);
            }

            $result = $this->dispatcher->dispatch($line);
            if ($result === CommandDispatcher::QUIT) {
                break;
            }
        }

        $this->output->showGoodbye();

        return 0;
    }
}
