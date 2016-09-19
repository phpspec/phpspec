<?php

namespace spec\PhpSpec\CodeGenerator\Writer;

use PhpSpec\Exception\Generator\NamedMethodNotFoundException;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\ClassFileAnalyser;
use Prophecy\Argument;

class TokenizedCodeWriterSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith(new ClassFileAnalyser());
    }

    function it_should_write_the_content_after_the_last_method()
    {
        $class = $this->getSingleMethodClass();
        $method = $this->getMethod();
        $result = $this->getClassWithNewMethodLast();

        $this->insertMethodLastInClass($class, $method)->shouldReturn($result);
    }

    function it_should_write_the_content_before_the_first_method()
    {
        $class = $this->getSingleMethodClass();
        $method = $this->getMethod();
        $result = $this->getClassWithNewMethodFirst();

        $this->insertMethodFirstInClass($class, $method)->shouldReturn($result);
    }

    function it_should_write_a_method_after_another_method()
    {
        $class = $this->getClassWithTwoMethods();
        $method = $this->getMethod();
        $result = $this->getClassWithNewMethodInMiddle();

        $this->insertAfterMethod($class, 'methodOne', $method)->shouldReturn($result);
    }

    function it_should_handle_no_methods_when_writing_method_at_end()
    {
        $class = $this->getClassWithNoMethods();
        $method = $this->getMethod();
        $result = $this->getClassWithOnlyNewMethod();

        $this->insertMethodLastInClass($class, $method)->shouldReturn($result);
    }

    function it_should_handle_no_methods_when_writing_method_at_start()
    {
        $class = $this->getClassWithNoMethods();
        $method = $this->getMethod();
        $result = $this->getClassWithOnlyNewMethod();

        $this->insertMethodFirstInClass($class, $method)->shouldReturn($result);
    }

    function it_should_throw_an_exception_if_a_specific_method_is_not_found()
    {
        $class = $this->getClassWithNoMethods();

        $exception = new NamedMethodNotFoundException('Target method not found');

        $this->shouldThrow($exception)->during('insertAfterMethod', array($class, 'methodOne', ''));
    }

    function it_should_generate_a_method_in_a_class_with_a_string_containing_braces()
    {
        $class = $this->getClassWithBraceText();
        $method = $this->getMethod();
        $result = $this->getClassWithBraceTextAndNewMethod();

        $this->insertMethodLastInClass($class, $method)->shouldReturn($result);
    }

    function it_should_generate_an_implements_for_a_class_in_same_namespace()
    {
        $class = $this->getClassWithNoInterfaces();
        $interface = 'MyNamespace\Bar';
        $result = $this->getClassWithImplementedInterfaceInSameNamespace();

        $this->insertImplementsInClass($class, $interface)->shouldReturn($result);
    }

    function it_should_generate_an_implements_for_a_class_with_existing_implements_declaration()
    {
        $class = $this->getClassWithImplementsOnMultipleLines();
        $interface = 'MyNamespace\Bar';
        $result = $this->getExceptedClassWithImplementsOnMultipleLines();

        $this->insertImplementsInClass($class, $interface)->shouldReturn($result);
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

    private function getMethod()
    {
        return <<<METHOD
    public function newMethod()
    {
        return 'newSomething';
    }
METHOD;
    }

    private function getClassWithNewMethodLast()
    {
        return <<<NEW_METHOD_LAST
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

    public function newMethod()
    {
        return 'newSomething';
    }
}
NEW_METHOD_LAST;
    }

    private function getClassWithNewMethodFirst()
    {
        return <<<NEW_METHOD_FIRST
<?php

namespace MyNamespace;

final class MyClass
{
    public function newMethod()
    {
        return 'newSomething';
    }

    /**
     * @return string
     */
    public function methodOne()
    {
        return 'something';
    }
}
NEW_METHOD_FIRST;
    }

    private function getClassWithTwoMethods()
    {
        return <<<TWO_METHOD_CLASS
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

    /**
     * @return string
     */
    public function methodTwo()
    {
        return 'something';
    }
}
TWO_METHOD_CLASS;
    }

    private function getClassWithNewMethodInMiddle()
    {
        return <<<MIDDLE_METHOD_CLASS
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

    public function newMethod()
    {
        return 'newSomething';
    }

    /**
     * @return string
     */
    public function methodTwo()
    {
        return 'something';
    }
}
MIDDLE_METHOD_CLASS;
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

    private function getClassWithOnlyNewMethod()
    {
        return <<<ONLY_NEW_METHOD_CLASS
<?php

namespace MyNamespace;

final class MyClass
{
    public function newMethod()
    {
        return 'newSomething';
    }
}
ONLY_NEW_METHOD_CLASS;
    }

    private function getClassWithBraceText()
    {
        return <<<'BRACE_TEXT_CLASS'
<?php

namespace MyNamespace;

final class MyClass
{
    public function braceMethod()
    {
        return "{$foo}";
    }
}
BRACE_TEXT_CLASS;
    }

    private function getClassWithBraceTextAndNewMethod()
    {
        return <<<'BRACE_TEXT_RESULT_CLASS'
<?php

namespace MyNamespace;

final class MyClass
{
    public function braceMethod()
    {
        return "{$foo}";
    }

    public function newMethod()
    {
        return 'newSomething';
    }
}
BRACE_TEXT_RESULT_CLASS;
    }

    private function getClassWithNoInterfaces()
    {
        return <<<NO_INTERFACES
<?php

namespace MyNamespace;

final class Foo
{
    public function __construct()
    {
    }
}
NO_INTERFACES;
    }

    private function getClassWithImplementedInterfaceInSameNamespace()
    {
        return <<<CLASS_WITH_INTERFACE
<?php

namespace MyNamespace;

final class Foo implements Bar
{
    public function __construct()
    {
    }
}
CLASS_WITH_INTERFACE;

    }

    private function getClassWithImplementsOnMultipleLines()
    {
        return <<<CLASS_WITH_MULTILINE_IMPLEMENTS
<?php

namespace MyNamespace;

final class Foo implements
    Bar,
    \ArrayAccess,
    \Countable
{
    public function __construct()
    {
    }
}
CLASS_WITH_MULTILINE_IMPLEMENTS;
    }

    private function getExceptedClassWithImplementsOnMultipleLines()
    {
        return <<<CLASS_WITH_MULTILINE_IMPLEMENTS
<?php

namespace MyNamespace;

final class Foo implements
    Bar,
    \ArrayAccess,
    \Countable,
    Bar
{
    public function __construct()
    {
    }
}
CLASS_WITH_MULTILINE_IMPLEMENTS;
    }
}
