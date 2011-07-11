<?php

namespace PHPSpec\Loader;

use \PHPSpec\Loader\ClassLoader,
    \PHPSpec\Loader\DirectoryLoader,
    \PHPSpec\Runner\Error;

class Loader
{
    public function factory($pathToSpecs)
    {
        if (is_dir($pathToSpecs)) {
            return new DirectoryLoader;
        } elseif (file_exists($pathToSpecs)) {
            return new ClassLoader;
        } else {
            throw new Error("File or directory $pathToSpecs not found.");
        }
    }
}