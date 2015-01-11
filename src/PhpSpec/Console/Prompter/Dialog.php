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
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Output\OutputInterface;

final class Dialog implements Prompter
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var DialogHelper
     */
    private $dialogHelper;

    /**
     * @param OutputInterface $output
     * @param DialogHelper    $dialogHelper
     */
    public function __construct(OutputInterface $output, DialogHelper $dialogHelper)
    {
        $this->output = $output;
        $this->dialogHelper = $dialogHelper;
    }

    /**
     * @param string  $question
     * @param boolean $default
     * @return boolean
     */
    public function askConfirmation($question, $default = true)
    {
        return $this->dialogHelper->askConfirmation($this->output, $question, $default);
    }
}
