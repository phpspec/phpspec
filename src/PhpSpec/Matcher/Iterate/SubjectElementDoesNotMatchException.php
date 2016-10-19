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

namespace PhpSpec\Matcher\Iterate;

class SubjectElementDoesNotMatchException extends \RuntimeException
{
    /**
     * @var int
     */
    private $elementNumber;

    /**
     * @var string
     */
    private $subjectKey;

    /**
     * @var string
     */
    private $subjectValue;

    /**
     * @var string
     */
    private $expectedKey;

    /**
     * @var string
     */
    private $expectedValue;

    /**
     * @param int $elementNumber
     * @param string $subjectKey
     * @param string $subjectValue
     * @param string $expectedKey
     * @param string $expectedValue
     */
    public function __construct($elementNumber, $subjectKey, $subjectValue, $expectedKey, $expectedValue)
    {
        $this->elementNumber = $elementNumber;
        $this->subjectKey = $subjectKey;
        $this->subjectValue = $subjectValue;
        $this->expectedKey = $expectedKey;
        $this->expectedValue = $expectedValue;

        parent::__construct('Subject element does not match with expected element.');
    }

    /**
     * @return int
     */
    public function getElementNumber()
    {
        return $this->elementNumber;
    }

    /**
     * @return string
     */
    public function getSubjectKey()
    {
        return $this->subjectKey;
    }

    /**
     * @return string
     */
    public function getSubjectValue()
    {
        return $this->subjectValue;
    }

    /**
     * @return string
     */
    public function getExpectedKey()
    {
        return $this->expectedKey;
    }

    /**
     * @return string
     */
    public function getExpectedValue()
    {
        return $this->expectedValue;
    }
}
