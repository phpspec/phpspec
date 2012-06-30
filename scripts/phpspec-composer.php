#!/usr/bin/php
<?php
/**
 * PHPSpec Composer Script
 */
ini_set('display_errors', 1);
error_reporting(E_ALL|E_STRICT);

if (is_file(__DIR__ . '/../vendor/.composer/autoload.php')) {
    require_once __DIR__ . '/../vendor/.composer/autoload.php';
} elseif (is_file(__DIR__ . '/../../../.composer/autoload.php')) {
    require_once __DIR__ . '/../../../.composer/autoload.php';
} elseif (is_file(__DIR__ . '/../.composer/autoload.php')) {
    require_once __DIR__ . '/../.composer/autoload.php';
} elseif (is_file(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../autoload.php';
}
else {
    require_once 'PHPSpec/Loader/UniversalClassLoader.php';
    include_once 'Mockery.php';

    $paths = explode(':', ini_get('include_path'));
    $loader = new \PHPSpec\Loader\UniversalClassLoader();
    $loader->registerNamespace('PHPSpec', $paths);
    $loader->registerNamespace('Mockery', $paths);

    $loader->registerPrefix('Text_', $paths);

    $loader->register();
}

$phpspec = new \PHPSpec\PHPSpec($argv);
$phpspec->execute();

