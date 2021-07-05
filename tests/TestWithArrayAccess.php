<?php

declare(strict_types=1);

namespace tests\spec\PhpSpec;

use ArrayAccess;

final class TestWithArrayAccess implements ArrayAccess
{
    public function offsetExists($offset) {
        if ($offset === 'true') {
            return true;
        }

        return false;
    }

    public function offsetGet($offset) {
        return $offset;
    }

    public function offsetSet($offset, $value) {

    }

    public function offsetUnset($offset) {

    }

}
