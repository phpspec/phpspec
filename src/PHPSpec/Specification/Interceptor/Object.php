<?php

namespace PHPSpec\Specification\Interceptor;

use \PHPSpec\Specification\Interceptor;

class Object extends Interceptor
{
    /**
     * Proxies call to specification and if method is a dsl call than it calls
     * the interceptor factory for the returned value
     * 
     * @param string $method
     * @param array $args
     * @return \PHPSpec\Specification\Interceptor|boolean
     */
    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }
        
        $object = $this->getActualValue();;
        return InterceptorFactory::create(
            call_user_func_array(array($object, $method), $args)
        );
    }
}