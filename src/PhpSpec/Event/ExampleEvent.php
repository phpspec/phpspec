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

use PhpSpec\Loader\Node\SpecificationNode;
use PhpSpec\Loader\Suite;
use PhpSpec\Loader\Node\ExampleNode;

/**
 * Class ExampleEvent holds the information about the example event
 */
class ExampleEvent extends BaseEvent implements PhpSpecEvent
{
    /**
     * Spec passed
     */
    public const PASSED  = 0;

    /**
     * Spec is pending
     */
    public const PENDING = 1;

    /**
     * Spec is skipped
     */
    public const SKIPPED = 2;

    /**
     * Spec failed
     */
    public const FAILED  = 3;

    /**
     * Spec is broken
     */
    public const BROKEN  = 4;

    public function __construct(
        private ExampleNode $example,
        // options below are not provided beforeExample
        private float $time = 0.0,
        private int $result = self::PASSED,
        private ?\Exception $exception = null
    )
    {
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
        return $this->getSpecification()?->getSuite();
    }

    
    public function getTitle(): string
    {
        return $this->example->getTitle();
    }

    
    public function getMessage(): string
    {
        return $this->exception?->getMessage() ?? '';
    }

    
    public function getBacktrace(): array
    {
        return $this->exception?->getTrace() ?? [];
    }

    
    public function getTime(): float
    {
        return $this->time;
    }

    
    public function getResult(): int
    {
        return $this->result;
    }

    public function getException(): ?\Exception
    {
        return $this->exception;
    }
}
