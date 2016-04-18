<?php

namespace spec\PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Transformer\TypeHintIndex;
use PhpSpec\ObjectBehavior;
use PhpSpec\Runner\CollaboratorManager;
use PhpSpec\Runner\Maintainer\CollaboratorsMaintainer;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\SpecificationInterface;
use PhpSpec\Wrapper\Collaborator;
use PhpSpec\Wrapper\Unwrapper;
use Prophecy\Argument;
use Prophecy\Prediction\CallPrediction;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionParameter;

/**
 * @mixin CollaboratorsMaintainer
 */
class CollaboratorsMaintainerSpec extends ObjectBehavior
{

    function let(Unwrapper $unwrapper, TypeHintIndex $typeHintIndex)
    {
        $this->beConstructedWith($unwrapper, $typeHintIndex);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('PhpSpec\Runner\Maintainer\CollaboratorsMaintainer');
    }

    /**
     * @param ExampleNode                $exampleNode
     * @param SpecificationInterface     $specification
     * @param MatcherManager             $matcherManager
     * @param CollaboratorManager        $collaboratorManager
     * @param SpecificationNode          $specificationNode
     * @param ReflectionClass            $reflectionClass
     * @param ReflectionClass            $reflectionParamClass
     * @param ReflectionFunctionAbstract $reflectionFunctionAbstract
     * @param Collaborator               $collaborator
     * @param ReflectionParameter        $reflectionParameter
     * @param TypeHintIndex              $typeHintIndex
     * @throws \PhpSpec\Exception\Wrapper\CollaboratorException
     */
    function it_prefers_types_from_signature(
        ExampleNode $exampleNode,
        SpecificationInterface $specification,
        MatcherManager $matcherManager,
        CollaboratorManager $collaboratorManager,
        SpecificationNode $specificationNode,
        ReflectionClass $reflectionClass,
        ReflectionClass $reflectionParamClass,
        ReflectionFunctionAbstract $reflectionFunctionAbstract,
        Collaborator $collaborator,
        ReflectionParameter $reflectionParameter,
        TypeHintIndex $typeHintIndex
    ) {
        $specificationNode->getClassReflection()->willReturn($reflectionClass);
        $exampleNode->getSpecification()->willReturn($specificationNode);
        $reflectionClass->hasMethod('let')->willReturn(false);
        $reflectionClass->getName()->willReturn('classname');
        $exampleNode->getFunctionReflection()->willReturn($reflectionFunctionAbstract);
        $docComment = '@param ExampleFixture $var';
        $collaboratorManager->has('var')->willReturn(true);
        $collaboratorManager->get('var')->willReturn($collaborator);
        $reflectionFunctionAbstract->getDocComment()->willReturn($docComment);
        $reflectionParameter->getName()->willReturn('var');
        $reflectionParameter->getClass()->willReturn($reflectionParamClass);
        $reflectionParameter->isArray()->willReturn(false);
        $reflectionParameter->isCallable()->willReturn(false);
        $reflectionFunctionAbstract->getParameters()->willReturn(array($reflectionParameter));

        $typeHintIndex->lookup(Argument::any(), Argument::any(), Argument::any())->willReturn(null);
        $reflectionParamClass->getName()->willReturn('spec\PhpSpec\Runner\Maintainer\Fixtures\ExampleFixture');
        $reflectionParameter->getDeclaringFunction()->willReturn($reflectionFunctionAbstract);
        $reflectionFunctionAbstract->getName()->willReturn('methodName');

        $this->prepare($exampleNode, $specification, $matcherManager, $collaboratorManager);

        $collaborator->__call('beADoubleOf', array('ExampleFixture'))->shouldNotHaveBeenCalled();
        $collaborator->__call('beADoubleOf', array('spec\PhpSpec\Runner\Maintainer\Fixtures\ExampleFixture'))->shouldHaveBeenCalled();
    }
}
