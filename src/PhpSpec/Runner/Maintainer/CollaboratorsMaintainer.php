<?php

namespace PhpSpec\Runner\Maintainer;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\SpecificationInterface;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;

use PhpSpec\Wrapper\Collaborator;
use PhpSpec\Wrapper\Unwrapper;

use Prophecy\Prophet;

class CollaboratorsMaintainer implements MaintainerInterface
{
    private static $docex = '#@param *([^ ]*) *\$([^ ]*)#';
    private $unwrapper;
    private $prophet;

    public function __construct(Unwrapper $unwrapper)
    {
        $this->unwrapper = $unwrapper;
    }

    public function supports(ExampleNode $example)
    {
        return true;
    }

    public function prepare(ExampleNode $example, SpecificationInterface $context,
                            MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        $this->prophet = new Prophet(null, $this->unwrapper, null);

        $classRefl = $example->getSpecification()->getClassReflection();

        if ($classRefl->hasMethod('let')) {
            $this->generateCollaborators($collaborators, $classRefl->getMethod('let'));
        }

        $this->generateCollaborators($collaborators, $example->getFunctionReflection());
    }

    public function teardown(ExampleNode $example, SpecificationInterface $context,
                             MatcherManager $matchers, CollaboratorManager $collaborators)
    {
        $this->prophet->checkPredictions();
    }

    public function getPriority()
    {
        return 50;
    }

    private function generateCollaborators(CollaboratorManager $collaborators, $function)
    {
        if ($comment = $function->getDocComment()) {
            $comment = str_replace("\r\n", "\n", $comment);
            foreach (explode("\n", trim($comment)) as $line) {
                if (preg_match(self::$docex, $line, $match)) {
                    $collaborator = $this->getOrCreateCollaborator($collaborators, $match[2]);
                    $collaborator->beADoubleOf($match[1]);
                }
            }
        }

        foreach ($function->getParameters() as $parameter) {
            $collaborator = $this->getOrCreateCollaborator($collaborators, $parameter->getName());
            if (null !== $class = $parameter->getClass()) {
                $collaborator->beADoubleOf($class->getName());
            }
        }
    }

    private function getOrCreateCollaborator(CollaboratorManager $collaborators, $name)
    {
        if (!$collaborators->has($name)) {
            $collaborator = new Collaborator($this->prophet->prophesize(), $this->unwrapper);
            $collaborators->set($name, $collaborator);
        }

        return $collaborators->get($name);
    }
}
