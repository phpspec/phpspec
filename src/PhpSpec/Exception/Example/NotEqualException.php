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

namespace PhpSpec\Exception\Example;

/**
 * Class NotEqualException holds information about the non-equal failure exception
 */
class NotEqualException extends FailureException
{
    /**
     * @var mixed
     */
    private $expected;

    /**
     * @var mixed
     */
    private $actual;

    /**
     * @var mixed
     */
    private $subject;

    /**
     * @var string
     */
    private $method;

    /**
     * @param string $message
     * @param mixed  $expected
     * @param mixed  $actual
     * @param mixed  $subject
     * @param string $method
     */
    public function __construct(string $message, $expected, $actual, $subject = null, $method = null)
    {
        parent::__construct($message);

        $this->expected = $expected;
        $this->actual   = $actual;
        $this->subject = $subject;
        $this->method = $method;
    }

    /**
     * @return mixed
     */
    public function getExpected()
    {
        return $this->expected;
    }

    /**
     * @return mixed
     */
    public function getActual()
    {
        return $this->actual;
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
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return var_export(array($this->expected, $this->actual), true);
    }
}
