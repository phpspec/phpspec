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

namespace PhpSpec\Loader;

<<<<<<< HEAD
use PhpSpec\Util\MethodAnalyser;
use PhpSpec\Locator\ResourceManagerInterface;
=======
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Locator\ResourceManager;

>>>>>>> Trigger an error when expected spec class is not found in file
use ReflectionClass;
use ReflectionMethod;

class ResourceLoader
{
    /**
     * @var \PhpSpec\Locator\ResourceManagerInterface
     */
    private $manager;
    /**
     * @var \PhpSpec\Util\MethodAnalyser
     */
    private $methodAnalyser;

    /**
     * @param ResourceManagerInterface $manager
     */
    public function __construct(ResourceManagerInterface $manager, MethodAnalyser $methodAnalyser = null)
    {
        $this->manager = $manager;
        $this->methodAnalyser = $methodAnalyser ?: new MethodAnalyser();
    }

    /**
     * @param string       $locator
     * @param integer|null $line
     *
     * @return Suite
     */
    public function load($locator, $line = null)
    {
        $suite = new Suite();
        foreach ($this->manager->locateResources($locator) as $resource) {
            $this->loadResouceIntoSuite($resource, $line, $suite);
        }

        return $suite;
    }

    /**
     * @param ResourceInterface $resource
     * @param integer|null      $line
     * @param Suite             $suite
     */
    private function loadResouceIntoSuite(ResourceInterface $resource, $line, Suite $suite)
    {
        if (!class_exists($resource->getSpecClassname()) && is_file($resource->getSpecFilename())) {
            require_once $resource->getSpecFilename();
        }

        if (!class_exists($resource->getSpecClassname())) {
            return;
        }

        $reflection = new ReflectionClass($resource->getSpecClassname());

        if ($reflection->isAbstract()) {
            return;
        }

        if (!$reflection->implementsInterface('PhpSpec\SpecificationInterface')) {
            return;
        }

        $spec = new Node\SpecificationNode($resource->getSrcClassname(), $reflection, $resource);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $this->addExampleToSpecification($method, $line, $spec);
        }

        $suite->addSpecification($spec);
    }

    /**
     * @param ReflectionMethod       $method
     * @param integer|null           $line
     * @param Node\SpecificationNode $spec
     */
    private function addExampleToSpecification(ReflectionMethod $method, $line, Node\SpecificationNode $spec)
    {
        if (!preg_match('/^(it|its)[^a-zA-Z]/', $method->getName())) {
            return;
        }

        if (null !== $line && !$this->lineIsInsideMethod($line, $method)) {
            return;
        }

        $example = new Node\ExampleNode(str_replace('_', ' ', $method->getName()), $method);

        if ($this->methodIsEmpty($method)) {
            $example->markAsPending();
        }

        $spec->addExample($example);
    }

    /**
     * @param $line
     * @param ReflectionMethod $method
     *
     * @return bool
     */
    private function lineIsInsideMethod($line, ReflectionMethod $method)
    {
        $line = intval($line);

        return $line >= $method->getStartLine() && $line <= $method->getEndLine();
    }
}
