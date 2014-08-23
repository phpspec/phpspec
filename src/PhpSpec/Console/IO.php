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

namespace PhpSpec\Console;

use PhpSpec\IO\IOInterface;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use PhpSpec\Config\OptionsConfig;

/**
 * Class IO deals with input and output from command line interaction
 */
class IO implements IOInterface
{
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output;

    /**
     * @var \Symfony\Component\Console\Helper\DialogHelper
     */
    private $dialogHelper;

    /**
     * @var string
     */
    private $lastMessage;

    /**
     * @var bool
     */
    private $hasTempString = false;

    /**
      * @var OptionsConfig
      */
    private $config;

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param DialogHelper    $dialogHelper
     * @param OptionsConfig   $config
     */
    public function __construct(InputInterface $input, OutputInterface $output, DialogHelper $dialogHelper, OptionsConfig $config)
    {
        $this->input   = $input;
        $this->output  = $output;
        $this->dialogHelper = $dialogHelper;
        $this->config  = $config;
    }

    /**
     * @return bool
     */
    public function isInteractive()
    {
        return $this->input->isInteractive();
    }

    /**
     * @return bool
     */
    public function isDecorated()
    {
        return $this->output->isDecorated();
    }

    /**
     * @return bool
     */
    public function isCodeGenerationEnabled()
    {
        if (!$this->isInteractive()) {
            return false;
        }

        return $this->config->isCodeGenerationEnabled()
            && !$this->input->getOption('no-code-generation');
    }

    /**
     * @return bool
     */
    public function isStopOnFailureEnabled()
    {
        return $this->config->isStopOnFailureEnabled()
            || $this->input->getOption('stop-on-failure');
    }

    /**
     * @return bool
     */
    public function isVerbose()
    {
        return OutputInterface::VERBOSITY_VERBOSE <= $this->output->getVerbosity();
    }

    /**
     * @return string
     */
    public function getLastWrittenMessage()
    {
        return $this->lastMessage;
    }

    /**
     * @param string       $message
     * @param integer|null $indent
     */
    public function writeln($message = '', $indent = null)
    {
        $this->write($message, $indent, true);
    }

    /**
     * @param string       $message
     * @param integer|null $indent
     */
    public function writeTemp($message, $indent = null)
    {
        $this->write($message, $indent);
        $this->hasTempString = true;
    }

    /**
     * @return null|string
     */
    public function cutTemp()
    {
        if (false === $this->hasTempString) {
            return;
        }

        $message = $this->lastMessage;
        $this->write('');

        return $message;
    }

    /**
     *
     */
    public function freezeTemp()
    {
        $this->write($this->lastMessage);
    }

    /**
     * @param string       $message
     * @param integer|null $indent
     * @param bool         $newline
     */
    public function write($message, $indent = null, $newline = false)
    {
        if ($this->hasTempString) {
            $this->hasTempString = false;
            $this->overwrite($message, $indent, $newline);

            return;
        }

        if (null !== $indent) {
            $message = $this->indentText($message, $indent);
        }

        $this->output->write($message, $newline);
        $this->lastMessage = $message.($newline ? "\n" : '');
    }

    /**
     * @param string       $message
     * @param integer|null $indent
     */
    public function overwriteln($message = '', $indent = null)
    {
        $this->overwrite($message, $indent, true);
    }

    /**
     * @param string       $message
     * @param integer|null $indent
     * @param bool         $newline
     */
    public function overwrite($message, $indent = null, $newline = false)
    {
        if (null !== $indent) {
            $message = $this->indentText($message, $indent);
        }

        $size = strlen(strip_tags($this->lastMessage));

        $this->write(str_repeat("\x08", $size));
        $this->write($message);

        $fill = $size - strlen(strip_tags($message));
        if ($fill > 0) {
            $this->write(str_repeat(' ', $fill));
            $this->write(str_repeat("\x08", $fill));
        }

        if ($newline) {
            $this->writeln();
        }

        $this->lastMessage = $message.($newline ? "\n" : '');
    }

    /**
     * @param string      $question
     * @param string|null $default
     *
     * @return string
     */
    public function ask($question, $default = null)
    {
        return $this->dialogHelper->ask($this->output, $question, $default);
    }

    /**
     * @param string $question
     * @param bool   $default
     *
     * @return Boolean
     */
    public function askConfirmation($question, $default = true)
    {
        $lines   = array();
        $lines[] = '<question>'.str_repeat(' ', 70)."</question>";
        foreach (explode("\n", wordwrap($question), 50) as $line) {
            $lines[] = '<question>  '.str_pad($line, 68).'</question>';
        }
        $lines[] = '<question>'.str_repeat(' ', 62).'</question> <value>'.
            ($default ? '[Y/n]' : '[y/N]').'</value> ';

        return $this->dialogHelper->askConfirmation(
            $this->output, implode("\n", $lines), $default
        );
    }

    /**
     * @param string       $question
     * @param callable     $validator
     * @param int|false    $attempts
     * @param string       $default
     *
     * @return string
     */
    public function askAndValidate($question, $validator, $attempts = false, $default = null)
    {
        return $this->dialogHelper->askAndValidate($this->output, $question, $validator, $attempts, $default);
    }

    /**
     * @param string  $text
     * @param integer $indent
     *
     * @return string
     */
    private function indentText($text, $indent)
    {
        return implode("\n", array_map(
            function ($line) use ($indent) {
                return str_repeat(' ', $indent).$line;
            },
            explode("\n", $text)
        ));
    }
}
