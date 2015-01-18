<?php

namespace Fake;

use PhpSpec\Console\Prompter as PrompterInterface;

class Prompter implements PrompterInterface
{
    private $answer;
    private $hasBeenAsked = false;

    public function setAnswer($answer)
    {
        $this->answer = $answer;
    }

    public function askConfirmation($question, $default = true)
    {
        $this->hasBeenAsked = true;
        return (bool)$this->answer;
    }

    public function hasBeenAsked()
    {
        return $this->hasBeenAsked;
    }
}
