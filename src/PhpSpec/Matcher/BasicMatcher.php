<?php

namespace PhpSpec\Matcher;

abstract class BasicMatcher implements MatcherInterface
{
    final public function positiveMatch($name, $subject, array $arguments)
    {
        if (false === $this->matches($subject, $arguments)) {
            throw $this->getFailureException($name, $subject, $arguments);
        }

        return $subject;
    }

    final public function negativeMatch($name, $subject, array $arguments)
    {
        if (true === $this->matches($subject, $arguments)) {
            throw $this->getNegativeFailureException($name, $subject, $arguments);
        }

        return $subject;
    }

    public function getPriority()
    {
        return 100;
    }

    abstract protected function matches($subject, array $arguments);
    abstract protected function getFailureException($name, $subject, array $arguments);
    abstract protected function getNegativeFailureException($name, $subject, array $arguments);
}
