<?php

require_once 'SpecHelper.php';

$options = new stdClass;
$options->recursive = true;
$options->specdoc = true;
$options->reporter = 'html';

PHPSpec_Runner::run($options);