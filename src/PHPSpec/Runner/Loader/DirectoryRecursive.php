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
 * to license@phpspec.org so we can send you a copy immediately.
 *
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 P�draic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 P�draic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Runner_Loader_DirectoryRecursive
{

    protected $_loaded = array();

    protected $_loadedClasses = array();

    protected $_filter = null;

    protected $_filterName = 'PHPSpec_Runner_Filter_Standard';

    protected $_directory = null;

    public function load($directory)
    {
        $this->_directory = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );

        $filterIterator = $this->getFilter();

        foreach ($filterIterator as $file) {
            $pathName = $file->getPathname();
            require_once $pathName;
            
            $class = $this->_getClassName($file);
            if (!in_array($class, $this->_loadedClasses)) {
                $classReflected = new ReflectionClass($class);
                $this->_loaded[] = $classReflected;
            }
        }

        return $this->_loaded;
    }

    public function getLoaded()
    {
        return $this->_loaded;
    }

    public function setFilterClass($filterName)
    {
        $this->_filterName = $filterName;
    }

    public function getFilter()
    {
        if (is_null($this->_filter)) {
            if (is_null($this->_directory)) {
                throw new Exception();
            }
            $reflection = new ReflectionClass($this->_filterName);
            $this->_filter = $reflection->newInstance($this->_directory);
        }
        return $this->_filter;
    }
    
    protected function _getClassName(splFileInfo $file)
    {
        $fileName = $file->getFilename();
        $className = substr($fileName, 0, strlen($fileName) - 4);
        $className2 = 'Describe' . substr($fileName, 0, strlen($fileName) - 8);
        $className3 = 'describe' . substr($fileName, 0, strlen($fileName) - 8);
        if (class_exists($className, false)) {
            $class = $className;
        } elseif (class_exists($className2, false)) {
            $class = $className2;
        } elseif (class_exists($className3, false)) {
            $class = $className3;
        }
        return $class;
    }
    

}