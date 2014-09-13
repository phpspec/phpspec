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

use PhpSpec\Util\MethodAnalyser;
use PhpSpec\Locator\ResourceManagerInterface;
use PhpSpec\Locator\ResourceInterface;
use PhpSpec\Locator\ResourceManager;

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
        $specClasses = $this->loadSpecClasses($resource->getSpecFilename());

        foreach ($specClasses as $specClassname => $reflection) {
            $spec = new Node\SpecificationNode($resource->getSrcClassname($specClassname), $reflection, $resource);

            if ($resource->getSpecClassname() != $specClassname) {
                $spec->addWarning(sprintf(
                    '%s is a badly named spec in %s',
                    $specClassname,
                    $resource->getSpecFilename()
                ));
            }

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $this->addExampleToSpecification($method, $line, $spec);
            }

            $suite->addSpecification($spec);
        }
    }

    /**
     * @param string $filename
     *
     * @return ReflectionClass[string]
     */
    private function loadSpecClasses($filename)
    {
        $classesBefore = get_declared_classes();
        require_once $filename;
        $classesLoaded = array_diff(get_declared_classes(), $classesBefore);
        $specs = array();

        foreach ($classesLoaded as $specClassname) {
            if ($specClassname === 'PhpSpec\ObjectBehavior') {
                continue;
            }

            $reflection = new ReflectionClass($specClassname);

            if ($reflection->isAbstract()) {
                continue;
            }

            if (!$reflection->implementsInterface('PhpSpec\SpecificationInterface')) {
                continue;
            }

            $specs[$specClassname] = $reflection;
        }

        return $specs;
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

        if ($this->methodAnalyser->reflectionMethodIsEmpty($method)) {
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
