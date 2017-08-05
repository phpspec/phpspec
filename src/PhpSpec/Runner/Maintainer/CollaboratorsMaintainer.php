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

use PhpSpec\CodeAnalysis\DisallowedNonObjectTypehintException;
use PhpSpec\Exception\Fracture\CollaboratorNotFoundException;
use PhpSpec\Exception\Wrapper\InvalidCollaboratorTypeException;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Transformer\TypeHintIndex;
use PhpSpec\Specification;
use PhpSpec\Runner\MatcherManager;
use PhpSpec\Runner\CollaboratorManager;
use PhpSpec\Wrapper\Collaborator;
use PhpSpec\Wrapper\Unwrapper;
use Prophecy\Exception\Doubler\ClassNotFoundException;
use Prophecy\Prophet;
use ReflectionException;

final class CollaboratorsMaintainer implements Maintainer
{
    /**
     * @var string
     */
    private static $docex = '#@param *([^ ]*) *\$([^ ]*)#';
    /**
     * @var Unwrapper
     */
    private $unwrapper;
    /**
     * @var Prophet
     */
    private $prophet;

    /**
     * @var TypeHintIndex
     */
    private $typeHintIndex;

    /**
     * @param Unwrapper $unwrapper
     * @param TypeHintIndex $typeHintIndex
     */
    public function __construct(Unwrapper $unwrapper, TypeHintIndex $typeHintIndex)
    {
        $this->unwrapper = $unwrapper;
        $this->typeHintIndex = $typeHintIndex;
    }

    /**
     * @param ExampleNode $example
     *
     * @return bool
     */
    public function supports(ExampleNode $example): bool
    {
        return true;
    }

    /**
     * @param ExampleNode            $example
     * @param Specification $context
     * @param MatcherManager         $matchers
     * @param CollaboratorManager    $collaborators
     */
    public function prepare(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ) {
        $this->prophet = new Prophet(null, $this->unwrapper, null);

        $classRefl = $example->getSpecification()->getClassReflection();

        if ($classRefl->hasMethod('let')) {
            $this->generateCollaborators($collaborators, $classRefl->getMethod('let'), $classRefl);
        }

        $this->generateCollaborators($collaborators, $example->getFunctionReflection(), $classRefl);
    }

    /**
     * @param ExampleNode            $example
     * @param Specification $context
     * @param MatcherManager         $matchers
     * @param CollaboratorManager    $collaborators
     */
    public function teardown(
        ExampleNode $example,
        Specification $context,
        MatcherManager $matchers,
        CollaboratorManager $collaborators
    ) {
        $this->prophet->checkPredictions();
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 50;
    }

    /**
     * @param CollaboratorManager         $collaborators
     * @param \ReflectionFunctionAbstract $function
     * @param \ReflectionClass            $classRefl
     */
    private function generateCollaborators(CollaboratorManager $collaborators, \ReflectionFunctionAbstract $function, \ReflectionClass $classRefl)
    {
        foreach ($function->getParameters() as $parameter) {

            $collaborator = $this->getOrCreateCollaborator($collaborators, $parameter->getName());
            try {
                if ($this->isUnsupportedTypeHinting($parameter)) {
                    throw new InvalidCollaboratorTypeException($parameter, $function);
                }
                if (($indexedClass = $this->getParameterTypeFromIndex($classRefl, $parameter))
                    || ($indexedClass = $this->getParameterTypeFromReflection($parameter))) {
                    $collaborator->beADoubleOf($indexedClass);
                }
            }
            catch (ClassNotFoundException $e) {
                $this->throwCollaboratorNotFound($e, null, $e->getClassname());
            }
            catch (DisallowedNonObjectTypehintException $e) {
                throw new InvalidCollaboratorTypeException($parameter, $function);
            }
        }
    }

    private function isUnsupportedTypeHinting(\ReflectionParameter $parameter)
    {
        return $parameter->isArray() || $parameter->isCallable();
    }

    /**
     * @param CollaboratorManager $collaborators
     * @param string              $name
     *
     * @return Collaborator
     */
    private function getOrCreateCollaborator(CollaboratorManager $collaborators, string $name): Collaborator
    {
        if (!$collaborators->has($name)) {
            $collaborator = new Collaborator($this->prophet->prophesize());
            $collaborators->set($name, $collaborator);
        }

        return $collaborators->get($name);
    }

    /**
     * @param \Exception $e
     * @param \ReflectionParameter|null $parameter
     * @param string $className
     * @throws CollaboratorNotFoundException
     */
    private function throwCollaboratorNotFound(\Exception $e, \ReflectionParameter $parameter = null, string $className = null)
    {
        throw new CollaboratorNotFoundException(
            sprintf('Collaborator does not exist '),
            0, $e,
            $parameter,
            $className
        );
    }

    /**
     * @param \ReflectionClass $classRefl
     * @param \ReflectionParameter $parameter
     *
     * @return string
     */
    private function getParameterTypeFromIndex(\ReflectionClass $classRefl, \ReflectionParameter $parameter): string
    {
        return $this->typeHintIndex->lookup(
            $classRefl->getName(),
            $parameter->getDeclaringFunction()->getName(),
            '$' . $parameter->getName()
        );
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return string
     */
    private function getParameterTypeFromReflection(\ReflectionParameter $parameter): string
    {
        try {
            if (null === $class = $parameter->getClass()) {
                return '';
            }

            return $class->getName();
        }
        catch (ReflectionException $e) {
            $this->throwCollaboratorNotFound($e, $parameter);
        }
    }

}
