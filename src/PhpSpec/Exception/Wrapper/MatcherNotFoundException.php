<?php

namespace PhpSpec\Exception\Wrapper;

use PhpSpec\Exception\Exception;

class MatcherNotFoundException extends Exception
{
    private $keyword;
    private $subject;
    private $arguments;

    public function __construct($message, $keyword, $subject, array $arguments)
    {
        parent::__construct($message);

        $this->keyword   = $keyword;
        $this->subject   = $subject;
        $this->arguments = $arguments;
    }

    public function getKeyword()
    {
        return $this->keyword;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getArguments()
    {
        return $this->arguments;
    }
}
