<?php

namespace PhpSpec\Exception\Example;

class PendingException extends ExampleException
{
    public function __construct($text = 'write pending example')
    {
        parent::__construct(sprintf('todo: %s', $text));
    }
}
