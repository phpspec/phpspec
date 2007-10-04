<?php

class PHPSpec_Runner_Phpt
{

    public function __construct()
    {
    }

    public function notify(PHPSpec_Specification $specification, PHPSpec_Expectation $expectation)
    {
        if ($specification->getMatcherResult() === $expectation->getExpectedMatcherResult()) {
            echo 'PASS';
            return;
        }
        if ($specification->getMatcherResult() === true) {
            echo $specification->getMatcherNegativeFailureMessage();
        }
        echo $specification->getMatcherFailureMessage();
    }
}