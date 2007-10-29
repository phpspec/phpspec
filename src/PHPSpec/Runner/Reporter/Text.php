<?php

class PHPSpec_Runner_Reporter_Text extends PHPSpec_Runner_Reporter
{

    public function toString()
    {
        $str = '';
        $str .= PHP_EOL . count($this->_result) . ' Specs Executed:' . PHP_EOL;
        $str .= count($this->_result->getPasses()) . ' Specs Passed' . PHP_EOL;
        $failed = $this->_result->getFailures();

        if (count($failed) > 0) {
            foreach ($failed as $failure) {
                $str .= $failure->getContextDescription();
                $str .= ' => ' . $failure->getSpecificationText();
                $str .= ' => ' . $failure->getFailedMessage();
                $str .= PHP_EOL;
            }
        }
        $str .= 'DONE' . PHP_EOL;
        return $str;
    }

    public function __toString()
    {
        return $this->toString();
    }

}