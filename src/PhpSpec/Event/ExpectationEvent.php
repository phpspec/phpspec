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

    /**
     * @var ExampleNode
     */
    private $example;

    /**
     * @var Matcher
     */
    private $matcher;

    /**
     * @var mixed
     */
    private $subject;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var integer
     */
    private $result;

    /**
     * @var \Exception
     */
    private $exception;

    /**
     * @param ExampleNode      $example
     * @param Matcher $matcher
     * @param mixed            $subject
     * @param string           $method
     * @param array            $arguments
     * @param integer          $result
     * @param \Exception       $exception
     */
    public function __construct(
        ExampleNode $example,
        Matcher $matcher,
        $subject,
        $method,
        $arguments,
        $result = self::PASSED,
        $exception = null
    ) {
        $this->example = $example;
        $this->matcher = $matcher;
        $this->subject = $subject;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->result = $result;
        $this->exception = $exception;
    }

    /**
     * @return Matcher
     */
    public function getMatcher(): Matcher
    {
        return $this->matcher;
    }

    /**
     * @return ExampleNode
     */
    public function getExample(): ExampleNode
    {
        return $this->example;
    }

    /**
     * @return SpecificationNode
     */
    public function getSpecification(): SpecificationNode
    {
        return $this->example->getSpecification();
    }

    /**
     * @return Suite
     */
    public function getSuite(): Suite
    {
        return $this->example->getSpecification()->getSuite();
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return \Exception|null
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @return integer
     */
    public function getResult(): int
    {
        return $this->result;
    }
}
