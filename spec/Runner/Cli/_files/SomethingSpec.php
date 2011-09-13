<?php

class DescribeSomething extends \PHPSpec\Context
{
    function itTriggersSomeError()
    {
        error_reporting(E_ALL);
        trigger_error('Some error');
    }
}