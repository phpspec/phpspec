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

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Factory
{
    /**
     * @var HelperSet
     */
    private $helperSet;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param HelperSet       $helperSet
     */
    public function __construct(InputInterface $input, OutputInterface $output, HelperSet $helperSet)
    {
        $this->helperSet = $helperSet;
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * @return \PhpSpec\Console\Prompter
     */
    public function getPrompter()
    {
        if ($this->helperSet->has('question')) {
            return new Question($this->input, $this->output, $this->helperSet->get('question'));
        }

        return new Dialog($this->output, $this->helperSet->get('dialog'));
    }
}
