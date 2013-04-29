<?php

namespace PhpSpec\Util;

use Symfony\Component\Finder\Finder;

class Filesystem
{
    public function pathExists($path)
    {
        return file_exists($path);
    }

    public function getFileContents($path)
    {
        return file_get_contents($path);
    }

    public function putFileContents($path, $content)
    {
        file_put_contents($path, $content);
    }

    public function isDirectory($path)
    {
        return is_dir($path);
    }

    public function makeDirectory($path)
    {
        mkdir($path, 0777, true);
    }

    public function findPhpFilesIn($path)
    {
        $finder = Finder::create()
            ->files()
            ->name('*.php')
            ->followLinks()
            ->sortByName()
            ->in($path)
        ;

        return iterator_to_array($finder);
    }
}
