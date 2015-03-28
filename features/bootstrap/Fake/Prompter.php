<?php

namespace Fake;

use PhpSpec\Console\Prompter as PrompterInterface;

class Prompter implements PrompterInterface
{
    private $answer;
    private $hasBeenAsked = false;
    private $question;

    public function setAnswer($answer)
    {
        $this->answer = $answer;
    }

    public function askConfirmation($question, $default = true)
    {
        $this->hasBeenAsked = true;
        $this->question = $question;
        return (bool)$this->answer;
    }

    public function hasBeenAsked($question = null)
    {
        if (!$question) {
            return $this->hasBeenAsked;
        }

        return $this->hasBeenAsked
            && preg_replace('/\s+/', ' ', trim(strip_tags($this->question))) == preg_replace('/\s+/', ' ', $question) ;
    }
}
