<?php

namespace PhpSpec\Exception\Fracture;

class ClassNotFoundException extends FractureException
{
    private $classname;

    public function __construct($message, $classname)
    {
        parent::__construct($message);

        $this->classname = $classname;
    }

    public function getClassname()
    {
        return $this->classname;
    }
}
