<?php

namespace PhpSpec\Misc;

class PassByReference
{
    public function test(&$value)
    {
        if (!is_array($value)) {
            throw new \Exception('value must be an array');
        }

        return $value;
    }
}
