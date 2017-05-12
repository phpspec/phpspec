<?php

namespace Fake;

use PhpSpec\Console\Prompter as PrompterInterface;

class Prompter implements PrompterInterface
{
    private $answers = array();
    private $hasBeenAsked = false;
    private $question;
    private $unansweredQuestions = false;

    public function setAnswer($answer)
    {
        $this->answers[] = $answer;
    }

    public function askConfirmation($question, $default = true)
    {
        $this->hasBeenAsked = true;
        $this->question = $question;

        $this->unansweredQuestions = count($this->answers) > 1;
        return (bool)array_shift($this->answers);
    }

    public function hasBeenAsked($question = null)
    {
        if (!$question) {
            return $this->hasBeenAsked;
        }

        return $this->hasBeenAsked
            && $this->normalise($this->question) == $this->normalise($question);
    }

    public function hasUnansweredQuestions()
    {
        return $this->unansweredQuestions;
    }

    /**
     * @return mixed
     */
    private function normalise($question)
    {
        return preg_replace('/\s+/', '', trim(strip_tags($question)));
    }
}
