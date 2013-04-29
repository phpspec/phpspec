<?php

namespace PhpSpec\Exception\Fracture;

class InterfaceNotImplementedException extends FractureException
{
    private $subject;
    private $interface;

    public function __construct($message, $subject, $interface)
    {
        parent::__construct($message);

        $this->subject   = $subject;
        $this->interface = $interface;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getInterface()
    {
        return $this->interface;
    }
}
