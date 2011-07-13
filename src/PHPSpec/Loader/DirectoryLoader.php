<?php
/**
 * PHPSpec
 *
 * LICENSE
 *
 * This file is subject to the GNU Lesser General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@phpspec.net so we can send you a copy immediately.
 *
 * @category  PHPSpec
 * @package   PHPSpec
 * @copyright Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Loader;

class DirectoryLoader extends ClassLoader
{
    
    public function load($specDir, $ignore = array())
    {
        $ignore = $this->lookForIgnoreConfig($specDir, $ignore);
        $directory = new \DirectoryIterator($specDir);
        $loaded = array();
        
        foreach ($directory as $file) {
            if ($file->isDot()) {
                continue;
            }
            
            if ($this->fileIsNotInIgnoreList($file, $ignore)) {
                if ($file->isDir()) {
                    $loaded = array_merge($loaded, $this->load($file->getRealpath(), $ignore));
                } else {
                    $example = parent::load($file->getRealpath());
                    if ($example !== false && $example !== array(false)) {
                        if (!is_array($example)) {
                            $example = array($example);
                        }
                        $loaded = array_merge($loaded, $example);
                    }
                }
            }
        }

        return $loaded;
    }
    
    private function fileIsNotInIgnoreList($file, $ignore)
    {
        return !in_array($file->getRealpath(), $ignore);
    }
    
    private function lookForIgnoreConfig($specDir, $ignore = array())
    {
        if (empty($ignore) && file_exists($specDir . '/.specignore')) {
            $ignore = array_merge($ignore, file($specDir . '/.specignore'));
            $cwd = getcwd();
            chdir($specDir);
            $ignore = array_map('realpath', $ignore);
            chdir($cwd);
        }
        return $ignore;
    }
}