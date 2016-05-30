<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Console\Prompter;

use PhpSpec\Console\Prompter;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use PhpSpec\Console\Manager as ConsoleManager;

final class Question implements Prompter
{
    /**
     * @var ConsoleManager
     */
    private $consoleManager;

    /**
     * @param ConsoleManager $consoleManager
     */
    public function __construct(ConsoleManager $consoleManager)
    {
        $this->consoleManager = $consoleManager;
    }

    /**
     * @param string  $question
     * @param boolean $default
     * @return boolean
     */
    public function askConfirmation($question, $default = true)
    {
        $input = $this->consoleManager->getInput();
        $output = $this->consoleManager->getOutput();
        $questionHelper = $this->consoleManager->getQuestionHelper();
        return (bool)$questionHelper->ask($input, $output, new ConfirmationQuestion($question, $default));
    }
}
