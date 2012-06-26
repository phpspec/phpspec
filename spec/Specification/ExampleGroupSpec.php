<?php

namespace Spec\PHPSpec\Specification;

require_once __DIR__ . '/_files/SomeContextSpec.php';

class DescribeExampleGroup extends \PHPSpec\Context
{
    public $itBehavesLike;
    
    function before()
    {
        $this->example = $this->spec(new \DescribeSomeContext);
    }
    
    public function itDoesntBehaveLikeAnotherObjectByDefault()
    {
        
        $this->example->behavesLikeAnotherObject()->should->beFalse();
    }
    
    public function itCanBehaveLikeAnotherObject()
    {
        $this->example = $this->spec(clone $this);
        $this->example->itBehavesLike = 'ExampleRunner';
        $this->example->behavesLikeAnotherObject()->should->beTrue();
    }
    
    
}