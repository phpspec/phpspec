<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;

class ClassFileAnalyserSpec extends ObjectBehavior
{
    function it_should_return_the_line_number_of_the_start_of_the_first_method()
    {
        $class = $this->getSingleMethodClass();
        $this->getStartLineOfFirstMethod($class)->shouldReturn(7);
    }

    function it_should_detect_if_class_has_a_method()
    {
        $class = $this->getSingleMethodClass();
        $this->classHasMethods($class)->shouldReturn(true);
    }

    function it_should_detect_if_class_has_no_methods()
    {
        $class = $this->getClassWithNoMethods();
        $this->classHasMethods($class)->shouldReturn(false);
    }

    function it_should_return_the_line_number_of_the_end_of_the_named_method()
    {
        $class = $this->getSingleMethodClass();
        $this->getEndLineOfNamedMethod($class, 'methodOne')->shouldReturn(13);
    }

    function it_should_return_the_line_number_of_the_end_of_the_last_method()
    {
    	$class = $this->getSingleMethodClassContainingAnonymousFunction();
    	$this->getEndLineOfLastMethod($class)->shouldReturn(12);
    }

    private function getSingleMethodClass()
    {
        return <<<SINGLE_METHOD_CLASS
<?php

namespace MyNamespace;

final class MyClass
{
    /**
     * @return string
     */
    public function methodOne()
    {
        return 'something';
    }
}
SINGLE_METHOD_CLASS;
    }

    private function getClassWithNoMethods()
    {
        return <<<NO_METHOD_CLASS
<?php

namespace MyNamespace;

final class MyClass
{
}
NO_METHOD_CLASS;
    }

    private function getSingleMethodClassContainingAnonymousFunction()
    {
    	return <<<SINGLE_METHOD_CLASS_CONTAINING_ANONYMOUS_FUNCTION
<?php

namespace MyNamespace;

class MyClass
{
    public function testAnAnonymousFunction()
    {
        return function () {
            return 'something';
        };
    }
 // com
}
/*
 comment }
 */

SINGLE_METHOD_CLASS_CONTAINING_ANONYMOUS_FUNCTION;
    }
}
