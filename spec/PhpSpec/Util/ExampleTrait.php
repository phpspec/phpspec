<?php

namespace spec\PhpSpec\Util;

trait ExampleTrait
{
    public function emptyMethodInTrait()
    {
    }
}

trait AnotherExampleTrait
{
    public function nonEmptyMethodInTrait()
    {
        return 'foo';
    }
}
