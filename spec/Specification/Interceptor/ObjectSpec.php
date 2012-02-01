<?php

use PHPSpec\Specification\Interceptor\Object as ObjectInterceptor;

class DescribeObject extends \PHPSpec\Context
{
    private $nonPublic = 42;
    
    public function itCanAccessNonPublicProperties()
    {
        $object = new self;
        $interceptor = new ObjectInterceptor($object);
        $interceptor->property('nonPublic')
                    ->should->be(42);
    }
    
}