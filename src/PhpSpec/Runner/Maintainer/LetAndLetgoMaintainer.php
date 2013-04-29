<?php

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

class LetAndLetgoMaintainer implements MaintainerInterface
{
    public function supports(ExampleNode $example)
    {
        return $example->getSpecification()->getClassReflection()->hasMethod('let')
            || $example->getSpecification()->getClassReflection()->hasMethod('letgo')
        ;
    }

    public function prepare(ExampleNode $example, SpecificationInterface $context,
                            MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        if (!$example->getSpecification()->getClassReflection()->hasMethod('let')) {
            return;
        }

        $reflection = $example->getSpecification()->getClassReflection()->getMethod('let');
        $reflection->invokeArgs($context, $collaborators->getArgumentsFor($reflection));
    }

    public function teardown(ExampleNode $example, SpecificationInterface $context,
                             MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        if (!$example->getSpecification()->getClassReflection()->hasMethod('letgo')) {
            return;
        }

        $reflection = $example->getSpecification()->getClassReflection()->getMethod('letgo');
        $reflection->invokeArgs($context, $collaborators->getArgumentsFor($reflection));
    }

    public function getPriority()
    {
        return 10;
    }
}
