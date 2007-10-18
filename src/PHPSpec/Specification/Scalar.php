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
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        throw new PHPSpec_Exception('unknown method called');
    }

    public function __get($name)
    {
        $dslResult = parent::__get($name);
        if (!is_null($dslResult)) {
            return $dslResult;
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