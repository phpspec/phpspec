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

namespace PhpSpec\Loader\Node;

use PhpSpec\Loader\Suite;
use PhpSpec\Locator\Resource;
use PhpSpec\Specification;
use ReflectionClass;

class SpecificationNode implements \Countable
{
    private Suite $suite;

    /**
     * @var ExampleNode[]
     */
    private array $examples = [];

    /**
     * @param string $title
     * @param ReflectionClass<Specification> $class
     * @param Resource $resource
     */
    public function __construct(
        private string $title,
        private ReflectionClass $class,
        private Resource $resource
    )
    {
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return ReflectionClass<Specification> */
    public function getClassReflection(): ReflectionClass
    {
        return $this->class;
    }

    public function getResource(): Resource
    {
        return $this->resource;
    }

    public function addExample(ExampleNode $example): void
    {
        $this->examples[] = $example;
        $example->setSpecification($this);
    }

    /**
     * @return ExampleNode[]
     */
    public function getExamples(): array
    {
        return $this->examples;
    }

    public function setSuite(Suite $suite) : void
    {
        $this->suite = $suite;
    }

    public function getSuite() : Suite
    {
        return $this->suite;
    }

    public function count(): int
    {
        return \count($this->examples);
    }
}
