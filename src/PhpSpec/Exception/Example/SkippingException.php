<?php

namespace PhpSpec\Exception\Example;

class SkippingException extends ExampleException
{
    /**
     * @param string $text
     */
    public function __construct($text = 'this example is skipped')
    {
        parent::__construct(sprintf('skipped: %s', $text));
    }
}
