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
        $class = $className;
        /**if (substr($className, strlen($className) - 4, 4) == '.php') {
            $classFile = $className;
            $class = substr($className, 0, strlen($className) - 4);
        } else {
            $classFile = $className . '.php';
        }

        if (!file_exists($classFile)) {
            if (preg_match("/^(describe)/i", $className)) {
                $classBase = substr($className, 8);
                $classFile = $classBase . 'Spec.php';
            }
        }**/
        
        if (substr($className, strlen($className)-4, 4) !== '.php' && strpos($className, 'Describe') == 0 && substr($className, strlen($className)-4, 4) !== 'Spec') {
        	$class = $className;
        	$classFile = $className . '.php';
        } elseif (substr($className, strlen($className)-4, 4) !== '.php' && substr($className, strlen($className)-4, 4) == 'Spec') {
            $classPartial = substr($className, 0, strlen($className)-4);
            $class = 'Describe' . $classPartial;
            $classFile = $className . '.php';
        } else {
            //var_dump(strpos($className, 'Describe')); exit;
        	throw new PHPSpec_Exception('Invalid class or filename given for a spec; spec could not be found using "' . $className . '"');
        }
        
        

        require_once $classFile;

        $classReflected = new ReflectionClass($class);

        $this->_loaded = array($classReflected);

        return $this->_loaded;
    }

    public function getLoaded()
    {
        return $this->_loaded;
    }

}