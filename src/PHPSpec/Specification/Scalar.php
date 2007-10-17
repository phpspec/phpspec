<?php

class PHPSpec_Specification_Scalar extends PHPSpec_Specification
{

    protected $_scalarValue = null;

    public function __construct($scalarValue = null)
    {
        if (!is_null($scalarValue)) {
            $this->_scalarValue = $scalarValue;
            $this->setActualValue($this->_scalarValue);
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
        throw new PHPSpec_Exception('unknown method called');
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
        throw new PHPSpec_Exception('unknown property requested');
    }

    public function getScalar()
    {
        if (is_null($this->_scalarValue)) {
            throw new PHPSpec_Exception('an scalar value has not yet been initialised');
        }
        return $this->_scalarValue;
    }

}