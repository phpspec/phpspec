<?php

interface PHPSpec_Matcher_Interface
{

    public function __construct($expected);

    public function matches($actual);

    public function getFailureMessage();

    public function getNegativeFailureMessage();

    public function getDescription();

}