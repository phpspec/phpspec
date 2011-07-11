<?php

namespace PHPSpec\Runner;

interface Formatter extends \SPLObserver
{
    public function output();
}