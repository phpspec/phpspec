<?php

namespace Matcher;

use PhpSpec\Exception\Example\FailureException;
use PhpSpec\Matcher\MatcherInterface;
use Symfony\Component\Console\Tester\ApplicationTester;


/**
 * Matcher class to test the status code from a console command
 *
 * @package Matcher
 */
class ApplicationStatusCodeMatcher implements MatcherInterface
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
    public function supports($name, $subject, array $arguments)
    {
        return ($name == 'haveStatusCode' && $subject instanceof ApplicationTester);
    }


    /**
     * Evaluates positive match.
     *
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     */
    public function positiveMatch($name, $subject, array $arguments)
    {
        $expected = $arguments[0];
        $actual = $subject->getStatusCode();
        if ($expected !== $actual) {
            throw new FailureException(sprintf(
                "Application status code did not contain expected '%s'. Actual output:\n'%s'" ,
                $expected,
                $actual
            ));
        }
    }


    /**
     * Evaluates negative match.
     *
     * @param string $name
     * @param mixed  $subject
     * @param array  $arguments
     */
    public function negativeMatch($name, $subject, array $arguments)
    {
        $expected = $arguments[0];
        $actual = $subject->getStatusCode();
        if ($expected === $actual) {
            throw new FailureException(sprintf(
                "Application status code did not contain expected '%s'. Actual output:\n'%s'" ,
                $expected,
                $actual
            ));
        }
    }


    /**
     * Returns matcher priority.
     *
     * @return integer
     */
    public function getPriority()
    {
        return 51;
    }
}