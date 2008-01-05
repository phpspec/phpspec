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
class PHPSpec_Runner_Loader_Classname
{

    protected $_loaded = array();

    public function load($className)
    {
        $class = '';
        
        /**
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
         */
        
        if (substr($className, strlen($className)-4, 4) !== '.php' && strpos($className, 'Describe') == 0 && substr($className, strlen($className)-4, 4) !== 'Spec') {
        	$class = $className;
        	$classFile = $className . '.php';
        } elseif (substr($className, strlen($className)-4, 4) !== '.php' && substr($className, strlen($className)-4, 4) == 'Spec') {
            $classPartial = substr($className, 0, strlen($className)-4);
            $class = 'Describe' . $classPartial;
            $classFile = $className . '.php';
        } elseif (substr($className, strlen($className)-4, 4) == '.php' && substr($className, 0, 8) == 'Describe') {
        	$class = substr($className, 0, strlen($className)-4);
        	$classFile = $className;
        } elseif (substr($className, strlen($className)-4, 4) == '.php' && substr($className, strlen($className)-8, 4) == 'Spec') {
            $classPartial = substr($className, 0, strlen($className)-8);
            $class = 'Describe' . $classPartial;
            $classFile = $className;
        } else {
        	throw new PHPSpec_Exception('Invalid class or filename given for a spec; spec could not be found using "' . $className . '"');
        }
        
        // existence test not implemented - let require call catch fatal error
        
        require_once $classFile;
        
        if (!class_exists($class, false)) {
            throw new PHPSpec_Exception('The class ' . $class . ' is not defined within the spec file ' . $classFile);
        }

        $classReflected = new ReflectionClass($class);

        $this->_loaded = array($classReflected);

        return $this->_loaded;
    }

    public function getLoaded()
    {
        return $this->_loaded;
    }

}