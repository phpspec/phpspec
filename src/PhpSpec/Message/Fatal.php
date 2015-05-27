<?php

namespace PhpSpec\Message;

class Fatal implements MessageInterface
{
    private $currentExample;

    public function setMessage($currentExample)
    {
        $this->currentExample = $currentExample;
    }

    public function getMessage()
    {
        return $this->currentExample;
    }
}
