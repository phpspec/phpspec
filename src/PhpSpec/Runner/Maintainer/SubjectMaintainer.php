<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\CodeAnalysis\AccessInspector;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\ObjectBehavior;
use PhpSpec\Specification;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;
use PhpSpec\Formatter\Presenter\Presenter;
use PhpSpec\Wrapper\SubjectContainer;
use PhpSpec\Wrapper\Unwrapper;
use PhpSpec\Wrapper\Wrapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SubjectMaintainer implements Maintainer
{
    public function __construct(
        private Presenter $presenter,
        private Unwrapper $unwrapper,
        private EventDispatcherInterface $dispatcher,
        private AccessInspector $accessInspector
    ) {
    }

    public function supports(ExampleNode $example): bool
    {
        return $example->getSpecification()->getClassReflection()->implementsInterface(
            SubjectContainer::class
        );
    }

    public function prepare(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ): void {
        $subjectFactory = new Wrapper($matchers, $this->presenter, $this->dispatcher, $example, $this->accessInspector);
        $subject = $subjectFactory->wrap(null);
        $subject->beAnInstanceOf(
            $example->getSpecification()->getResource()->getSrcClassname()
        );

        if ($context instanceof ObjectBehavior) {
            $context->setSpecificationSubject($subject);
        }
    }

    public function teardown(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ): void {
    }

    public function getPriority(): int
    {
        return 100;
    }
}
