<?php

namespace PhpSpec\Console;

use PhpSpec\IO\IOInterface;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\HelperSet;

class IO implements IOInterface
{
    private $input;
    private $output;
    private $helpers;
    private $lastMessage;
    private $hasTempString = false;

    public function __construct(InputInterface $input, OutputInterface $output, HelperSet $helpers)
    {
        $this->input   = $input;
        $this->output  = $output;
        $this->helpers = $helpers;
    }

    public function isInteractive()
    {
        return $this->input->isInteractive();
    }

    public function isDecorated()
    {
        return $this->output->isDecorated();
    }

    public function isCodeGenerationEnabled()
    {
        return $this->input->isInteractive()
            && !$this->input->getOption('no-code-generation');
    }
    
    public function isVerbose()
    {
        return OutputInterface::VERBOSITY_VERBOSE === $this->output->getVerbosity();
    }

    public function getLastWrittenMessage()
    {
        return $this->lastMessage;
    }

    public function writeln($message = '', $indent = null)
    {
        $this->write($message, $indent, true);
    }

    public function writeTemp($message, $indent = null)
    {
        $this->write($message, $indent);
        $this->hasTempString = true;
    }

    public function cutTemp()
    {
        if (false === $this->hasTempString) {
            return;
        }

        $message = $this->lastMessage;
        $this->write('');

        return $message;
    }

    public function freezeTemp()
    {
        $this->write($this->lastMessage);
    }

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

    public function overwriteln($message = '', $indent = null)
    {
        $this->overwrite($message, $indent, true);
    }

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

    public function ask($question, $default = null)
    {
        return $this->helpers->get('dialog')->ask($this->output, $question, $default);
    }

    public function askConfirmation($question, $default = true)
    {
        $lines   = array();
        $lines[] = '<question>'.str_repeat(' ', 70)."</question>";
        foreach (explode("\n", wordwrap($question), 50) as $line) {
            $lines[] = '<question>  '.str_pad($line, 68).'</question>';
        }
        $lines[] = '<question>'.str_repeat(' ', 62).'</question> <value>'.
            ($default ? '[Y/n]' : '[y/N]').'</value> ';

        return $this->helpers->get('dialog')->askConfirmation(
            $this->output, implode("\n", $lines), $default
        );
    }

    public function askAndValidate($question, $validator, $attempts = false, $default = null)
    {
        return $this->helpers->get('dialog')->askAndValidate($this->output, $question, $validator, $attempts, $default);
    }

    private function indentText($text, $indent)
    {
        return implode("\n", array_map(
            function($line) use($indent) {
                return str_repeat(' ', $indent).$line;
            },
            explode("\n", $text)
        ));
    }
}
