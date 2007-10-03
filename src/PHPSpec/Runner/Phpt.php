<?php

class PHPSpec_Runner_Phpt
{

    public function __construct()
    {
    }

    public function notify(PHPSpec_Specification $specification)
    {
        if ($specification->getMatcherResult() === true) {
            assert(true);
            return;
        }
        assert(false); // not to figure out how phpt works under the hood ;-)
    }
}