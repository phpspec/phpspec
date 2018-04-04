<?php

namespace spec\PhpSpec\Loader;

use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Locator\Resource;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\ObjectBehavior;
use PhpSpec\Util\MethodAnalyser;

class ResourceLoaderSpec extends ObjectBehavior
{

    public function let(
        ResourceManager $resourceManager,
        MethodAnalyser $methodAnalyser
    ) {
        $this->beConstructedWith(
            $resourceManager,
            $methodAnalyser
        );
    }

    public function it_should_add_error_to_specification_when_mismatched_defined_spec_path_and_defined_namespace(
        Resource $resource,
        ResourceManager $resourceManager
    ) {
        $expectedMessage = 'Spec path does not match defined namespace.';

        $resource->getSrcClassname()->willReturn('src/classname');
        $resource->getSrcFilename()->willReturn('src/filename.php');
        $resource->getSpecClassname()->willReturn('spec/mismatched/classname');
        $resource->getSpecFilename()->willReturn('spec/filename.php');

        $resourceManager->locateResources('locator')->willReturn([$resource]);

        $this->load('locator')->shouldMatchExampleClosureExceptionMessage($expectedMessage);
    }

    public function getMatchers(): array
    {
        return [
            'matchExampleClosureExceptionMessage' => function (\PhpSpec\Loader\Suite $suite, string $expectedMessage) {
                /**@var $spec SpecificationNode[] */
                $specs = $suite->getSpecifications();

                $examples = $specs[0]->getExamples();
                $reflectionFunction = $examples[0]->getFunctionReflection();

                try {
                    $reflectionFunction->invokeArgs([]);
                } catch (\Error $e) {
                    return $e->getMessage() === $expectedMessage;
                }

                return false;
            },
        ];
    }
}