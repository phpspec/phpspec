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

class TypeFailureException extends FailureException
{
    protected $subject;

    protected $expectedType;

    /**
     * @param string $message
     * @param mixed  $subject
     * @param string $expectedType
     */
    public function __construct($message, $subject, $expectedType)
    {
        $this->subject = $subject;
        $this->expectedType = $expectedType;

        parent::__construct($message);
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
    public function getExpectedType()
    {
        return $this->expectedType;
    }
}
