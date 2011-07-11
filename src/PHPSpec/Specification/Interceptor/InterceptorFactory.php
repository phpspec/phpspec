<?php

namespace PHPSpec\Specification\Interceptor;

class InterceptorFactory
{
    public static function create()
    {
        $args = func_get_args();
        $value = array_shift($args);
        
        if (is_callable($value)) {
            $spec = new Closure($value);
            
        } elseif ((is_string($value) && class_exists($value, true)) ||
                   is_object($value)) {
            $spec = new Object($value);
        } else {
            $spec = new Scalar($value);
        }

        return $spec;
    }
}