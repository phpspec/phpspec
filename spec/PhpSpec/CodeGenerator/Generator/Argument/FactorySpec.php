<?php

namespace spec\PhpSpec\CodeGenerator\Generator\Argument;

use PhpSpec\CodeGenerator\Generator\Argument\Argument;
use PhpSpec\CodeGenerator\Generator\Argument\Factory;
use PhpSpec\ObjectBehavior;

class FactorySpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(Factory::class);
    }

    function it_can_create_arguments_from_reflection_parameters()
    {
        $reflectionClass = new \ReflectionClass(Fixture::class);
        $reflectionParams = $reflectionClass->getMethod('message')->getParameters();

        $idArgument = new Argument('id', 'int');
        $idArgument->setDefaultValue(null);

        $messageArgument = new Argument('message', 'string');
        $messageArgument->setDefaultValue('Hello');

        $expectedArguments = [
            new Argument('name', 'string'),
            $idArgument,
            $messageArgument
        ];

        $this->fromReflectionParams($reflectionParams)->shouldBeLike($expectedArguments);
    }

    function it_can_create_arguments_from_reflection_parameters_containing_class_type_hints()
    {
        $reflectionClass = new \ReflectionClass(Fixture::class);
        $reflectionParams = $reflectionClass->getMethod('receive')->getParameters();

        $valueArgument = new Argument('value', new \ReflectionClass(Argument::class));
        $valueArgument->setDefaultValue(null);

        $expectedArguments = [
            new Argument('object', new \ReflectionClass(\stdClass::class)),
            $valueArgument
        ];

        $this->fromReflectionParams($reflectionParams)->shouldBeLike($expectedArguments);
    }
}

interface Fixture
{
    public function message(string $name, int $id = null, string $message = 'Hello');

    public function receive(\stdClass $object, Argument $value = null);
}