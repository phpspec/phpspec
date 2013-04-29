<?php

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\Subject;

class SubjectMaintainer implements MaintainerInterface
{
    private $presenter;
    private $unwrapper;

    public function __construct(PresenterInterface $presenter, Unwrapper $unwrapper)
    {
        $this->presenter = $presenter;
        $this->unwrapper = $unwrapper;
    }

    public function supports(ExampleNode $example)
    {
        return $example->getSpecification()->getClassReflection()->implementsInterface(
            'PhpSpec\Wrapper\SubjectContainerInterface'
        );
    }

    public function prepare(ExampleNode $example, SpecificationInterface $context,
                            MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        $subject = new Subject(null, $matchers, $this->unwrapper, $this->presenter);
        $subject->beAnInstanceOf(
            $example->getSpecification()->getResource()->getSrcClassname()
        );

        $context->setSpecificationSubject($subject);
    }

    public function teardown(ExampleNode $example, SpecificationInterface $context,
                             MatcherManager $matchers, CollaboratorManager $collaborators)
    {
    }

    public function getPriority()
    {
        return 100;
    }
}
