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

class Filesystem
{
    /**
     * @param string $path
     *
     * @return bool
     */
    public function pathExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function getFileContents(string $path): string
    {
        return file_get_contents($path);
    }

    /**
     * @param string $path
     * @param string $content
     */
    public function putFileContents(string $path, string $content)
    {
        file_put_contents($path, $content);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * @param string $path
     */
    public function makeDirectory(string $path)
    {
        mkdir($path, 0777, true);
    }

    /**
     * @param string $path
     *
     * @return \SplFileInfo[]
     */
    public function findSpecFilesIn(string $path)
    {
        $finder = Finder::create()
            ->files()
            ->name('*Spec.php')
            ->followLinks()
            ->sortByName()
            ->in($path)
        ;

        return iterator_to_array($finder);
    }
}
