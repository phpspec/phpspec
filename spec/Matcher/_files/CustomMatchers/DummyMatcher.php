<?php
namespace Spec\PHPSpec\Matcher\_files\CustomMatchers;

use \PHPSpec\Matcher;


class DummyMatcher implements Matcher {
    /**
     * Returns failure message in case we are using should
     * 
     * @return string
     */
    public function getFailureMessage()
    {
        return 'expected dummy, got dummer.';
    }

    /**
     * Returns failure message in case we are using should not
     * 
     * @return string
     */
    public function getNegativeFailureMessage()
    {
        return 'expected not to be dummer, but it is not dummy';
    }

    /**
     * Returns the matcher description
     * 
     * @return string
     */
    public function getDescription()
    {
        return 'dummy';
    }
}