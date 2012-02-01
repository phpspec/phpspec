<?php
namespace Spec\PHPSpec\Matcher\_files\CustomMatchers;

use \PHPSpec\Matcher;


class Be implements Matcher {
    /**
     * Returns failure message in case we are using should
     * 
     * @return string
     */
    public function getFailureMessage()
    {
        return 'This is fake be.';
    }

    /**
     * Returns failure message in case we are using should not
     * 
     * @return string
     */
    public function getNegativeFailureMessage()
    {
        return "This ain't the no fake be.";
    }

    /**
     * Returns the matcher description
     * 
     * @return string
     */
    public function getDescription()
    {
        return 'Fake be.';
    }
}