<?php

require_once  __DIR__ . '/../src/PHPSpec/Loader/UniversalClassLoader.php';
$loader = new \PHPSpec\Loader\UniversalClassLoader();
$loader->registerNamespace('PHPSpec', __DIR__ . '/../src');
$loader->register();

\PHPSpec\PHPSpec::setTestingPHPSpec(true);