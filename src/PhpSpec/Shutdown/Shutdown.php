<?php

namespace PhpSpec\Shutdown;

use PhpSpec\Message\Example;

class Shutdown
{
    /**
     * @var Example
     */
    private $message;

    public function __construct(Example $message) {
        register_shutdown_function(array($this, 'updateConsole'));
        $this->message = $message;
    }

    public function updateConsole()
    {
//        echo $this->message->getExampleMessage() . PHP_EOL;
    }
}
