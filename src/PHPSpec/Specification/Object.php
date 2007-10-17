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
        if (in_array($method, array('should', 'shouldNot'))) {
            $this->_expectation->$method();
            return $this;
        }
        if (in_array($method, array('be'))) {
            if (empty($args)) {
                return $this;
            }
        }
        if (in_array($method, array('equal', 'be', 'beAnInstanceOf', 'beGreaterThan', 'beTrue', 'beFalse', 'beEmpty'))) {
            $this->setExpectedValue(array_shift($args));
            $this->_createMatcher($method);
            $this->_performMatching();
            return;
        }
        $this->setActualValue(call_user_func_array(array($this->_interrogator, $method), $args));
        return $this;
    }

    public function __get($name)
    {
        if (in_array($name, array('should', 'shouldNot', 'a', 'an', 'of', 'be'))) {
            if (in_array($name, array('should', 'shouldNot', 'be'))) {
                switch ($name) {
                    case 'should':
                        $this->should();
                        break;
                    case 'shouldNot':
                        $this->shouldNot();
                        break;
                    case 'be':
                        $this->be();
                        break;
                }
            }
            return $this;
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