<?php

interface PHPSpec_Matcher_Interface
{

    public function __construct($expected);

    public function matches($actual);

}