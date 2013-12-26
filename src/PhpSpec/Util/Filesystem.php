<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Util;

use Symfony\Component\Finder\Finder;

/**
 * Class Filesystem
 * @package PhpSpec\Util
 */
class Filesystem
{
    /**
     * @param $path
     * @return bool
     */
    public function pathExists($path)
    {
        return file_exists($path);
    }

    /**
     * @param $path
     * @return string
     */
    public function getFileContents($path)
    {
        return file_get_contents($path);
    }

    /**
     * @param $path
     * @param $content
     */
    public function putFileContents($path, $content)
    {
        file_put_contents($path, $content);
    }

    /**
     * @param $path
     * @return bool
     */
    public function isDirectory($path)
    {
        return is_dir($path);
    }

    /**
     * @param $path
     */
    public function makeDirectory($path)
    {
        mkdir($path, 0777, true);
    }

    /**
     * @param $path
     * @return array
     */
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
