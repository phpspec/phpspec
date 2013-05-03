<?php

namespace PhpSpec\Wrapper;

use Prophecy\Prophecy\RevealerInterface;
use Prophecy\Prophecy\ProphecyInterface;

class Unwrapper implements RevealerInterface
{
    public function unwrapAll(array $arguments)
    {
        if (null === $arguments) {
            return array();
        }

        return array_map(array($this, 'unwrapOne'), $arguments);
    }

    public function unwrapOne($argument)
    {
        if (is_array($argument)) {
            return array_map(array($this, 'unwrapOne'), $argument);
        }

        if (!is_object($argument)) {
            return $argument;
        }

        if ($argument instanceof WrapperInterface) {
            return $argument->getWrappedObject();
        }

        if ($argument instanceof ProphecyInterface) {
            $argument = $argument->reveal();
        }

        return $argument;
    }

    public function reveal($value)
    {
        return $this->unwrapOne($value);
    }
}
