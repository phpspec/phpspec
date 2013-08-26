<?php

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

use PhpSpec\Formatter\Presenter\PresenterInterface;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\Wrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubjectMaintainer implements MaintainerInterface
{
    private $presenter;
    private $unwrapper;
    private $dispatcher;

    public function __construct(PresenterInterface $presenter, Unwrapper $unwrapper, EventDispatcherInterface $dispatcher)
    {
        $this->presenter = $presenter;
        $this->unwrapper = $unwrapper;
        $this->dispatcher = $dispatcher;
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
        $subjectFactory = new Wrapper($matchers, $this->presenter, $this->dispatcher, $example);
        $subject = $subjectFactory->wrap(null);
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
