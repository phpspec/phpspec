<?php

namespace Spec\PHPSpec\Matcher;

use \PHPSpec\Matcher\ContainText;

class DescribeContainText extends \PHPSpec\Context
{
    private $matcher;

    function before()
    {
        $this->matcher = $this->spec(new ContainText('some foo text'));
        $this->matcher->matches('foo');
    }

    function itShouldReturnADescriptionWithExpectedValue()
    {
        $this->matcher->getDescription()->should->be('contain text \'foo\'');
    }

    function itShouldReturnAMeaningfulFailureMessageIfRequested()
       {
   	    $this->matcher->matches('bar');
   	    $this->matcher->getFailureMessage()->should->be(
   	        "expected to contain:" . PHP_EOL .
   	        "'some foo text', got:" . PHP_EOL . "'bar' (using containText())"
   	    );
   	}

   	function itShouldReturnAMeaningulNegativeFailureMessageIfRequired()
       {
   	    $this->matcher->matches('foo');
   		$this->matcher->getNegativeFailureMessage()->should->be(
   		    "expected text:" . PHP_EOL .
   		    "'some foo text' to not be contained in:" . PHP_EOL .
   		    "'foo' (using containText())"
   		);
   	}

   	function itReturnsTrueIfTextExists()
   	{
   	    $this->matcher->matches('foo')->should->beTrue();
   	}

   	function itReturnsFalseIfTextDoesNotExist()
   	{
   	    $this->matcher->matches('zoo')->should->beFalse();
   	}
}
