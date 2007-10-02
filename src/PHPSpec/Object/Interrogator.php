<?php

class PHPSpec_Object_Interrogator
{

    protected $_sourceObject = null;

    public function __construct()
    {
        $args = func_get_args();
        $object = array_shift($args);
        if (!is_object($object)) {
            if (is_string($object) && class_exists($object, false)) {
                $class = new ReflectionClass($object);
                if ($class->isInstantiable()) {
                    $object = call_user_func_array(array($class, 'newInstance'), $args);
                } else {
                    throw new Exception('class cannot be instantiated');
                }
            } else {
                throw new Exception('not a valid class type');
            }
        }
        $this->_sourceObject = $object;
    }

    public function getSourceObject()
    {
        return $this->_sourceObject;
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this->_sourceObject, $method), $args);
    }

    public function __get($name)
    {
        return $this->_sourceObject->{$name};
    }

    public function __set($name, $value)
    {
        $this->_sourceObject->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset($this->_sourceObject->{$name});
    }

    public function __unset($name)
    {
        unset($this->_sourceObject->{$name});
    }

}