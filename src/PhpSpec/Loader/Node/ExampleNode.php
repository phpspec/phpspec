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
    private SpecificationNode $specification;

    private bool $isPending = false;

    public function __construct(
        private string $title,
        private ReflectionFunctionAbstract $function
    )
    {
    }

    public function setTitle(string $title) : void
    {
      $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function markAsPending(bool $isPending = true): void
    {
        $this->isPending = $isPending;
    }

    public function isPending(): bool
    {
        return $this->isPending;
    }

    public function getFunctionReflection(): ReflectionFunctionAbstract
    {
        return $this->function;
    }

    public function setSpecification(SpecificationNode $specification): void
    {
        $this->specification = $specification;
    }

    public function getSpecification() : SpecificationNode
    {
        return $this->specification;
    }

    public function getLineNumber(): int
    {
        return $this->function->isClosure() ? 0 : $this->function->getStartLine();
    }
}
