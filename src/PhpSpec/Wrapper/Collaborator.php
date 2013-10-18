<?php

namespace PhpSpec\Wrapper;

use Prophecy\Prophecy\ObjectProphecy;

class Collaborator implements WrapperInterface
{
    private $prophecy;

    public function __construct(ObjectProphecy $prophecy)
    {
        $this->prophecy  = $prophecy;
    }

    public function beADoubleOf($classOrInterface)
    {
        if (interface_exists($classOrInterface)) {
            $this->prophecy->willImplement($classOrInterface);
        } else {
            $this->prophecy->willExtend($classOrInterface);
        }
    }

    public function beConstructedWith(array $arguments = null)
    {
        $this->prophecy->willBeConstructedWith($arguments);
    }

    public function implement($interface)
    {
        $this->prophecy->willImplement($interface);
    }

    public function __call($method, array $arguments)
    {
        return call_user_func_array(array($this->prophecy, '__call'), array($method, $arguments));
    }

    public function __set($parameter, $value)
    {
        $this->prophecy->$parameter = $value;
    }

    public function __get($parameter)
    {
        return $this->prophecy->$parameter;
    }

    public function getWrappedObject()
    {
        return $this->prophecy->reveal();
    }
}
