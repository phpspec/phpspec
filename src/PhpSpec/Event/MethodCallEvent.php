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

namespace PhpSpec\Event;

use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Suite;

/**
 * Class MethodCallEvent holds information about method call events
 */
class MethodCallEvent extends BaseEvent implements PhpSpecEvent
{
    private ExampleNode $example;

    private mixed $subject;

    private string $method;

    private array $arguments;

    private mixed $returnValue;

    public function __construct(ExampleNode $example, mixed $subject, string $method, array $arguments, mixed $returnValue = null)
    {
        $this->example = $example;
        $this->subject = $subject;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->returnValue = $returnValue;
    }

    public function getExample(): ExampleNode
    {
        return $this->example;
    }

    public function getSpecification(): ?SpecificationNode
    {
        return $this->example->getSpecification();
    }

    public function getSuite(): ?Suite
    {
        return $this->example->getSpecification()?->getSuite();
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getReturnValue(): mixed
    {
        return $this->returnValue;
    }
}
