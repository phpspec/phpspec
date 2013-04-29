<?php

namespace PhpSpec\Matcher;

interface MatcherInterface
{
    /**
     * Checks if matcher supports provided subject and matcher name.
     *
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     *
     * @return Boolean
     */
    public function supports($name, $subject, array $arguments);

    /**
     * Evaluates positive match.
     *
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     */
    public function positiveMatch($name, $subject, array $arguments);

    /**
     * Evaluates negative match.
     *
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     */
    public function negativeMatch($name, $subject, array $arguments);

    /**
     * Returns matcher priority.
     *
     * @return integer
     */
    public function getPriority();
}
