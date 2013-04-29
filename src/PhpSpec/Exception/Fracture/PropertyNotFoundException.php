<?php

namespace PhpSpec\Exception\Fracture;

class PropertyNotFoundException extends FractureException
{
    private $subject;
    private $property;

    public function __construct($message, $subject, $property)
    {
        parent::__construct($message);

        $this->subject = $subject;
        $this->property  = $property;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function getProperty()
    {
        return $this->property;
    }
}
