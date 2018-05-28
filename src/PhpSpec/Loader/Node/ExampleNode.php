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

use ReflectionFunctionAbstract;

class ExampleNode
{
    /**
     * @var string
     */
    private $title;
    /**
     * @var \ReflectionFunctionAbstract
     */
    private $function;
    /**
     * @var SpecificationNode|null
     */
    private $specification;
    /**
     * @var bool
     */
    private $isPending = false;

    /**
     * @param string                     $title
     * @param ReflectionFunctionAbstract $function
     */
    public function __construct(string $title, ReflectionFunctionAbstract $function)
    {
        $this->setTitle($title);
        $this->function = $function;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title)
    {
      $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param bool $isPending
     */
    public function markAsPending(bool $isPending = true): void
    {
        $this->isPending = $isPending;
    }

    /**
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->isPending;
    }

    /**
     * @return ReflectionFunctionAbstract
     */
    public function getFunctionReflection(): ReflectionFunctionAbstract
    {
        return $this->function;
    }

    /**
     * @param SpecificationNode $specification
     */
    public function setSpecification(SpecificationNode $specification): void
    {
        $this->specification = $specification;
    }

    /**
     * @return SpecificationNode|null
     */
    public function getSpecification()
    {
        return $this->specification;
    }

    /**
     * @return int
     */
    public function getLineNumber(): int
    {
        return $this->function->isClosure() ? 0 : $this->function->getStartLine();
    }
}
