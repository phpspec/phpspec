<?php

namespace PHPSpec\Runner;

use \PHPSpec\World;

interface Runner
{
    public function run(World $world);
}