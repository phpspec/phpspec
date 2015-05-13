<?php

namespace PhpSpec\Message;

class Example implements ExampleInterface
{
    private $currentExample;

    public function setExampleMessage($currentExample) {
        $this->currentExample = $currentExample;
    }

    public function getExampleMessage() {
        return $this->currentExample;
    }
}
