<?php

namespace spec\PhpSpec\Util;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

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

    function it_should_return_the_namespace_of_a_class()
    {
        $class = $this->getClassWithNoMethods();
        $this->getClassNamespace($class)->shouldReturn('Foo\Bar');
    }

    function it_should_return_the_last_line_of_the_class_declaration()
    {
        $this->getLastLineOfClassDeclaration($this->getClassThatImplementsInterface())->shouldReturn(5);
        $this->getLastLineOfClassDeclaration($this->getClassThatImplementsInterfaceOnMultiLine())->shouldReturn(7);
    }

    function it_should_detect_if_class_implements_an_interface()
    {
        $this->classImplementsInterface($this->getClassThatImplementsInterface())->shouldReturn(true);
        $this->classImplementsInterface($this->getClassWithNoMethods())->shouldReturn(false);
    }

    function it_should_return_the_line_number_of_the_last_use_statement()
    {
        $this->getLastLineOfUseStatements($this->getClassContainingUseStatements())->shouldReturn(6);
        $this->getLastLineOfUseStatements($this->getClassWithNoMethods())->shouldReturn(null);
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

namespace Foo\Bar;

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

    private function getClassThatImplementsInterface()
    {
        return <<<CLASS_IMPLEMENTING_INTERFACE
<?php

namespace Foo\Bar;

final class MyClass implements MyInterface
{
}
CLASS_IMPLEMENTING_INTERFACE;
    }

    private function getClassThatImplementsInterfaceOnMultiLine()
    {
        return <<<CLASS_IMPLEMENTING_INTERFACE
<?php

namespace Foo\Bar;

final class MyClass implements
    \ArrayAccess
    MyInterface
{
}
CLASS_IMPLEMENTING_INTERFACE;
    }

    private function getClassContainingUseStatements()
    {
        return <<<CLASS_WITH_USE_STATEMENTS
<?php

namespace Foo\Bar;

use Foo\Bar\Baz;
use Baz\Foo\Bar;

class MyClass implements Baz, Bar
{
}
CLASS_WITH_USE_STATEMENTS;

    }
}
