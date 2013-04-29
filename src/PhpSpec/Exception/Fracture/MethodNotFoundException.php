<?php

namespace PhpSpec\Exception\Fracture;

class MethodNotFoundException extends FractureException
{
    private $subject;
    private $method;
    private $arguments;

    public function __construct($message, $subject, $method, array $arguments = array())
    {
        parent::__construct($message);

        $this->subject   = $subject;
        $this->method    = $method;
        $this->arguments = $arguments;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getMethodName()
    {
        return $this->method;
    }

    public function getArguments()
    {
        return $this->arguments;
    }
}
