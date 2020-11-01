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

class MethodFailureException extends NotEqualException
{
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
    public function __construct(string $message, $expected, $actual, $subject, $method)
    {
        parent::__construct($message, $expected, $actual);

        $this->subject = $subject;
        $this->method = $method;
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
}
