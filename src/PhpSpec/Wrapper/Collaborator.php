<?php

namespace PhpSpec\Wrapper;

use Prophecy\Prophecy\ObjectProphecy;

class Collaborator implements WrapperInterface
{
    private $prophecy;
    private $unwrapper;

    public function __construct(ObjectProphecy $prophecy, Unwrapper $unwrapper)
    {
        $this->prophecy  = $prophecy;
        $this->unwrapper = $unwrapper;
    }

    public function beADoubleOf($classOrInterface)
    {
        if (interface_exists($classOrInterface)) {
            $this->prophecy->willImplement($classOrInterface);
        } else {
            $this->prophecy->willExtend($classOrInterface);
        }
    }

    public function __call($method, array $arguments)
    {
        $arguments = $this->unwrapper->unwrapAll($arguments);

        return call_user_func_array(array($this->prophecy, $method), $arguments);
    }

    public function __set($parameter, $value)
    {
        $value = $this->unwrapper->unwrapOne($value);

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
