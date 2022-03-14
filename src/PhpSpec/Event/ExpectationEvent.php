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
use PhpSpec\Matcher\Matcher;

/**
 * Class ExpectationEvent holds information about the expectation event
 */
final class ExpectationEvent extends BaseEvent implements PhpSpecEvent
{
    /**
     * Expectation passed
     */
    const PASSED  = 0;

    /**
     * Expectation failed
     */
    const FAILED  = 1;

    /**
     * Expectation broken
     */
    const BROKEN  = 2;

    private ExampleNode $example;

    private Matcher $matcher;

    
    private $subject;

    private string $method;

    private array $arguments;

    private int $result;

    private ?\Exception $exception;

    public function __construct(
        ExampleNode $example,
        Matcher $matcher,
        $subject,
        string $method,
        array $arguments,
        int $result = self::PASSED,
        ?\Exception $exception = null
    ) {
        $this->example = $example;
        $this->matcher = $matcher;
        $this->subject = $subject;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->result = $result;
        $this->exception = $exception;
    }

    
    public function getMatcher(): Matcher
    {
        return $this->matcher;
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

    
    public function getSubject()
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


    public function getException(): ?\Exception
    {
        return $this->exception;
    }

    
    public function getResult(): int
    {
        return $this->result;
    }
}
