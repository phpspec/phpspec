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
namespace PHPSpec\Runner\Loader;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class DirectoryRecursive
{

    /**
     * Array with the instances of the loaded examples
     * 
     * @var array
     */
    protected $_loaded = array();

    /**
     * @FIXME! this property seems to be redundant and misleading.
     * 
     * @var array
     */
    protected $_loadedClasses = array();

    /**
     * The filter object
     * 
     * @var PHPSpec\Runner\Filter\Standard
     */
    protected $_filter = null;

    /**
     * The name of the filter class
     * 
     * @var string
     */
    protected $_filterName = '\PHPSpec\Runner\Filter\Standard';

    /**
     * The directory being scanned
     * 
     * @var \RecursiveIteratorIterator
     */
    protected $_directory = null;

    /**
     * Loads the directory
     * 
     * @param string $directory
     * @return array
     */
    public function load($directory)
    {
        $this->_directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        $filterIterator = $this->getFilter();

        foreach ($filterIterator as $file) {
            $pathName = $file->getPathname();
            require_once $pathName;
            
            $class = $this->_getClassName($file);
            if ($class && !in_array($class, $this->_loadedClasses)) {
                $classReflected = new \ReflectionClass($class);
                $this->_loaded[] = $classReflected;
            }
        }

        return $this->_loaded;
    }

    /**
     * Gets the loaded examples
     * 
     * @return array
     */
    public function getLoaded()
    {
        return $this->_loaded;
    }

    /**
     * Sets the filter name
     * 
     * @param string $filterName
     */
    public function setFilterClass($filterName)
    {
        $this->_filterName = $filterName;
    }

    /**
     * Gets the filter object
     * 
     * @throws \Exception
     * @return \PHPSpec\Runner\Filter\Standard
     */
    public function getFilter()
    {
        if (is_null($this->_filter)) {
            if (is_null($this->_directory)) {
                throw new \Exception();
            }
            // hack for 5.3.2 bug
            if ($this->_filterName === '\PHPSpec\Runner\Filter\Standard') {
                return new \PHPSpec\Runner\Filter\Standard($this->_directory);
            }
            $reflection = new \ReflectionClass($this->_filterName);
            $this->_filter = $reflection->newInstance($this->_directory);
        }
        return $this->_filter;
    }
    
    /**
     * Gets a class name based on the file
     * 
     * @param \SplFileInfo $file
     * @return string
     */
    protected function _getClassName(\SplFileInfo $file)
    {
        $fileName = $file->getFilename();
        $className = substr($fileName, 0, strlen($fileName) - 4);
        $secondName = 'Describe' . substr($fileName, 0, strlen($fileName) - 8);
        $thirdName = 'describe' . substr($fileName, 0, strlen($fileName) - 8);
        if (class_exists($className, false)) {
            $class = $className;
        } elseif (class_exists($secondName, false)) {
            $class = $secondName;
        } elseif (class_exists($thirdName, false)) {
            $class = $thirdName;
        }
        if (isset($class)) {
            return $class;
        }
    }

}