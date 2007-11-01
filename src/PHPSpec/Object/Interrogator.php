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
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Object_Interrogator
{

    protected $_sourceObject = null;

    public function __construct()
    {
        $args = func_get_args();
        $object = array_shift($args);
        if (!is_object($object)) {
            if (is_string($object) && class_exists($object, false)) {
                $class = new ReflectionClass($object);
                if ($class->isInstantiable()) {
                    $object = call_user_func_array(array($class, 'newInstance'), $args);
                } else {
                    throw new PHPSpec_Exception('class cannot be instantiated');
                }
            } else {
                throw new PHPSpec_Exception('not a valid class type');
            }
        }
        $this->_sourceObject = $object;
    }

    public function getSourceObject()
    {
        return $this->_sourceObject;
    }

    public function __call($method, $args)
    {
        return call_user_func_array(array($this->_sourceObject, $method), $args);
    }

    public function __get($name)
    {
        return $this->_sourceObject->{$name};
    }

    public function __set($name, $value)
    {
        $this->_sourceObject->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset($this->_sourceObject->{$name});
    }

    public function __unset($name)
    {
        unset($this->_sourceObject->{$name});
    }

}