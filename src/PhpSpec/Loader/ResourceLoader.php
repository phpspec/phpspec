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

use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Event\ResourceEvent;
use PhpSpec\Locator\Resource;
use PhpSpec\Specification;
use PhpSpec\Specification\ErrorSpecification;
use PhpSpec\Util\MethodAnalyser;
use PhpSpec\Locator\ResourceManager;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ResourceLoader
{
    private ResourceManager $manager;

    private MethodAnalyser $methodAnalyser;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ResourceManager $manager,
        MethodAnalyser $methodAnalyser,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->manager = $manager;
        $this->methodAnalyser = $methodAnalyser;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(string $locator = '', int $line = null): Suite
    {
        $suite = new Suite();
        foreach ($this->manager->locateResources($locator) as $resource) {
            if (!class_exists($resource->getSpecClassname(), false) && is_file($resource->getSpecFilename())) {
                try {
                    /** @psalm-suppress UnresolvableInclude we are reasonably confident it is only loading a class definition */
                    require_once StreamWrapper::wrapPath($resource->getSpecFilename());
                }
                catch (\Error $e) {
                    $this->addErrorThrowingExampleToSuite($resource, $suite, $e);
                    continue;
                }
            } else {
                $this->eventDispatcher->dispatch(
                    ResourceEvent::ignored($resource),
                    'resourceIgnored'
                );
            }

            if (!class_exists($resource->getSpecClassname(), false)) {
                continue;
            }

            $reflection = new ReflectionClass($resource->getSpecClassname());

            if ($reflection->isAbstract()) {
                continue;
            }
            if (!$reflection->implementsInterface(Specification::class)) {
                continue;
            }

            $spec = new SpecificationNode($resource->getSrcClassname(), $reflection, $resource);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (!preg_match('/^(it|its)[^a-zA-Z]/', $method->getName())) {
                    continue;
                }
                if (null !== $line && !$this->lineIsInsideMethod($line, $method)) {
                    continue;
                }

                $example = new ExampleNode(str_replace('_', ' ', $method->getName()), $method);

                if ($this->methodAnalyser->reflectionMethodIsEmpty($method)) {
                    $example->markAsPending();
                }

                $spec->addExample($example);
            }

            $suite->addSpecification($spec);
        }

        return $suite;
    }

    private function lineIsInsideMethod(int $line, ReflectionMethod $method): bool
    {
        $line = \intval($line);

        return $line >= $method->getStartLine() && $line <= $method->getEndLine();
    }

    private function addErrorThrowingExampleToSuite(Resource $resource, Suite $suite, \Error $error): void
    {
        $reflection = new ReflectionClass(ErrorSpecification::class);
        $spec = new SpecificationNode($resource->getSrcClassname(), $reflection, $resource);

        $errorFunction = new \ReflectionFunction(
            function () use ($error) {
                throw $error;
            }
        );
        $example = new ExampleNode('Loading specification', $errorFunction);

        $spec->addExample($example);
        $suite->addSpecification($spec);
    }
}
