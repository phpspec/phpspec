<?php

namespace PHPSpec\Util;

use \PHPSpec\Specification\Interceptor\InterceptorFactory;

class SpecIterator implements \Iterator
{
    protected $_value;
    
    public function __construct($value)
    {
        $this->_value = $value;
    }
    
    public function next()
    {
        next($this->_value);
    }
    public function current()
    {
        return current($this->_value);
    }
    public function valid()
    {
        return !@is_null($this->_value[key($this->_value)]);
    }
    public function key()
    {
        return key($this->_value);
    }
    public function rewind()
    {
        reset($this->_value);
    }

    public function withEach(\Closure $yield)
    {
        $elements = array();
        if (is_array($this->_value) || $this->_value instanceof Iterator) {
            foreach ($this->_value as $key => $value) {
                $elements[] = $yield(InterceptorFactory::create($value));
            }
            return $elements;
        }
        throw new \PHPSpec\Exception('Not an traversable item');
    }
}