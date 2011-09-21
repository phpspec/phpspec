<?php

namespace Spec\PHPspec\Matcher;

class DescribePredicate extends \PHPSpec\Context
{
    function itReadsAMethodStartingWithIsUsingShouldAndBe()
    {
        $dummy = $this->spec(new Dummy);
        $dummy->should->beValid();
    }
    
    function itReadsAMethodStartingWithIsUsingShouldNotAndBe()
    {
        $dummy = $this->spec(new Dummy);
        $dummy->shouldNot->beInvalid();
    }
}

class Dummy
{
    public function isValid()
    {
        return true;
    }
    
    public function isInvalid()
    {
        return false;
    }
}