<?php

namespace PhpSpec\State;

use PhpSpec\Message\Example;
use PhpSpec\Message\Spec;

class CurrentState
{

    protected $currentExample;
    protected $currentSpec;

    public function currentExample()
    {
        return $this->currentExample;
    }

    public function currentSpec()
    {
        return $this->currentSpec;
    }

    public function updateCurrentState(Example $example, Spec $spec)
    {
        // TODO: write logic here
    }
}
