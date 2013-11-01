<?php

namespace PhpSpec\Wrapper\Subject\Expectation;

use PhpSpec\Matcher\MatcherInterface;

class NegativeException
{
    private $matcher;

    public function __construct(MatcherInterface $matcher)
    {
        $this->matcher = $matcher;
    }

    public function match($alias, $subject, array $arguments = array())
    {
        return $this->matcher->negativeMatch($alias, $subject, $arguments);
    }
}
