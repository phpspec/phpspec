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
            if (!class_exists($resource->getSpecClassname()) && is_file($resource->getSpecFilename())) {
                require_once $resource->getSpecFilename();
            }
            if (!class_exists($resource->getSpecClassname())) {
                continue;
            }

            $reflection = new ReflectionClass($resource->getSpecClassname());

            if ($reflection->isAbstract()) {
                continue;
            }
            if (!$reflection->implementsInterface('PhpSpec\SpecificationInterface')) {
                continue;
            }

            $spec = new Node\SpecificationNode($resource->getSrcClassname(), $reflection, $resource);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!preg_match('/^[Ii](?:ts|t)\w/', $method->getName())) {
                    continue;
                }
                if (null !== $line && !$this->lineIsInsideMethod($line, $method)) {
                    continue;
                }

                $example = new Node\ExampleNode(ucfirst($this->smartExampleNameSplitter($method->getName())), $method);

                if ($this->methodAnalyser->reflectionMethodIsEmpty($method)) {
                    $example->markAsPending();
                }

                $spec->addExample($example);
            }

            $suite->addSpecification($spec);
        }

        return $suite;
    }

    /**
     * @param int              $line
     * @param ReflectionMethod $method
     *
     * @return bool
     */
    private function lineIsInsideMethod($line, ReflectionMethod $method)
    {
        $line = intval($line);

        return $line >= $method->getStartLine() && $line <= $method->getEndLine();
    }

    /**
     * Detects underscore_case vs camelCase or PascalCase and splits into words accordingly.
     *
     * @param $name
     *
     * @return string
     *
     * @author Michael Cummings <mgcummings@yahoo.com>
     */
    private function smartExampleNameSplitter($name)
    {
        if('_' === $name[2] || '_' === $name[3]) {
            return str_replace('_', ' ', $name);
        }
        $split = preg_split('/(^[^A-Z]+|[A-Z][^A-Z]+)/', $name, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        return strtolower(implode(' ', $split));
    }
}
