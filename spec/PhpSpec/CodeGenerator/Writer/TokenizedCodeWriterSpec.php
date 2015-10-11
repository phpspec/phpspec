<?php

namespace spec\PhpSpec\CodeGenerator\Writer;

use PhpSpec\Exception\Generator\NamedMethodNotFoundException;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TokenizedCodeWriterSpec extends ObjectBehavior
{
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
}
