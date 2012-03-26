#!/usr/bin/php
<?php
/**
 * PHPSpec Composer Script
 */
ini_set('display_errors', 1);
error_reporting(E_ALL|E_STRICT);

require_once __DIR__ . '/../vendor/.composer/autoload.php';
set_include_path(__DIR__.'/../src');
$phpspec = new \PHPSpec\PHPSpec($argv);
$phpspec->execute();

