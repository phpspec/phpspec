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
class Classname
{

    /**
     * Array with the instances of the loaded examples
     * 
     * @var array
     */
    protected $_loaded = array();

    /**
     * Loads classes
     * 
     * Convention; For loading spec files and classes on command line
     * 
     * Convention #1: Classnames are reflected in Filenames which follow the
     * format of "Describe*", e.g. "DescribeNewBowlingGame" defined in
     * "DescribeNewBowlingGame.php".
     * 
     * Convention #2: Classnames are reflected in the Filename by removing
     * the "Describe" prefix and appending a "Spec" suffix, e.g.
     * "DescribeNewBowlingGame" defined in "NewBowlingGameSpec.php".
     * 
     * Conventions are case sensitive. Both Spec and Describe are expected
     * to commence with a capital letter. On the command line, the .php
     * prefix is optional.
     * 
     * @param string $className
     * @param string $pathToFile
     * @throws \PHPSpec\Exception
     * @return array
     */
    public function load($className, $pathToFile)
    {
        $class = '';
        
        if (substr($className, strlen($className)-4, 4) !== '.php' &&
            strpos($className, 'Describe') == 0 &&
            substr($className, strlen($className)-4, 4) !== 'Spec') {
            $class = $className;
            $classFile = $className . '.php';
        } elseif (substr($className, strlen($className)-4, 4) !== '.php' &&
                  substr($className, strlen($className)-4, 4) == 'Spec') {
            $classPartial = substr($className, 0, strlen($className)-4);
            $class = 'Describe' . $classPartial;
            $classFile = $className . '.php';
        } elseif (substr($className, strlen($className)-4, 4) == '.php' &&
                  substr($className, 0, 8) == 'Describe') {
            $class = substr($className, 0, strlen($className)-4);
            $classFile = $className;
        } elseif (substr($className, strlen($className)-4, 4) == '.php' &&
                  substr($className, strlen($className)-8, 4) == 'Spec') {
            $classPartial = substr($className, 0, strlen($className)-8);
            $class = 'Describe' . $classPartial;
            $classFile = $className;
        } else {
            throw new \PHPSpec\Exception(
                'Invalid class or filename given for a spec; ' .
                'spec could not be found using "' . $className . '"'
            );
        }
        
        // existence test not implemented - let require call catch fatal error
        if (!is_readable($pathToFile . '/' . $classFile)) {
            die(
                'phpspec: cannot open ' .
                $pathToFile . '/' . $classFile . PHP_EOL
            );
        }
        require_once $pathToFile . '/' . $classFile;
        
        if (!class_exists($class, false)) {
            throw new \PHPSpec\Exception(
                'The class ' . $class .
                ' is not defined within the spec file ' . $classFile
            );
        }

        $classReflected = new \ReflectionClass($class);

        $this->_loaded = array($classReflected);

        return $this->_loaded;
    }

    /**
     * Gets the loaded examples in array
     * 
     * @return array
     */
    public function getLoaded()
    {
        return $this->_loaded;
    }

}