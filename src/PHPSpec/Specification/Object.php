<?php

class PHPSpec_Specification_Object extends PHPSpec_Specification
{

    protected $_interrogator = null;

    public function __construct(PHPSpec_Object_Interrogator $interrogator = null)
    {
        if (!is_null($interrogator)) {
            $this->_interrogator = $interrogator;
        }
        $this->_expectation = new PHPSpec_Expectation;
    }

    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        $this->setActualValue(call_user_func_array(array($this->_interrogator, $method), $args));
        return $this;
    }

    public function __get($name)
    {
        $dslResult = parent::__get($name);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        $this->setActualValue($this->_interrogator->{$name});
        return $this;
    }

    public function getInterrogator()
    {
        if (is_null($this->_interrogator)) {
            throw new PHPSpec_Exception('an Interrogator has not yet been created');
        }
        return $this->_interrogator;
    }

    protected function __set($name, $value)
    {
        $this->_interrogator->{$name} = $value;
    }

    protected function __isset($name)
    {
        return isset($this->_interrogator->{$name});
    }

    protected function __unset($name)
    {
        unset($this->_interrogator->{$name});
    }

}