<?php

class DescribeExampleGroup extends \PHPSpec\Context
{
    public function itDoesntBehaveLikeAnotherObjectByDefault()
    {
        $example = $this->spec(clone $this);
        $example->behavesLikeAnotherObject()->should->beFalse();
    }
    
}