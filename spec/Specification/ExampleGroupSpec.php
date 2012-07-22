<?php

namespace Spec\PHPSpec\Specification;

require_once __DIR__ . '/_files/SomeContextSpec.php';
require_once __DIR__ . '/_files/SomeSharedExample.php';
require_once __DIR__ . '/_files/SomethingElse.php';

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
        $this->example->itBehavesLike = '\SomeSharedExample';
        $this->example->behavesLikeAnotherObject()->should->beTrue();
    }
    
    public function itCantBehaveLikeAnObjectThatIsNotASharedExample()
    {
        $example = new \DescribeSomeContext;
        $example->itBehavesLike = '\SomethingElse';
        $this->spec(function() use ($example) {
            $example->behavesLikeAnotherObject();
        })->should->throwException('\PHPSpec\Specification\Exception', '\SomethingElse is not a SharedExample');
    }
}
