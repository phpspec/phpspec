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
namespace PHPSpec\Object;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Interrogator
{

    /**
     * The objected being intercepted by the interrogator
     * 
     * @var object
     */
    protected $_sourceObject = null;

    /**
     * Creates an interrogator using the first parameter as the source object
     * and the following arguments as constructor arguments
     * 
     * @throws \PHPSpec\Exception
     */
    public function __construct()
    {
        $args = func_get_args();
        $object = array_shift($args);
        if (!is_object($object)) {
            if (is_string($object) && class_exists($object, false)) {
                $class = new \ReflectionClass($object);
                if ($class->isInstantiable()) {
                    $object = call_user_func_array(
                        array($class, 'newInstance'), $args
                    );
                } else {
                    throw new \PHPSpec\Exception("Class can't be instantiated");
                }
            } else {
                throw new \PHPSpec\Exception('Not a valid class type');
            }
        }
        $this->_sourceObject = $object;
    }

    /**
     * Gets the source object
     * 
     * @return object
     */
    public function getSourceObject()
    {
        return $this->_sourceObject;
    }

    /**
     * Proxies function call to source object
     * 
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array(
            array($this->_sourceObject, $method), $args
        );
    }

    /**
     * Gets the value of a property of the source object
     * 
     * @param string $name
     */
    public function __get($name)
    {
        return $this->_sourceObject->{$name};
    }

    /**
     * Sets the value of a property of the source object
     * 
     * @param string $name
     * @param string $value
     */
    public function __set($name, $value)
    {
        $this->_sourceObject->{$name} = $value;
    }

    /**
     * Checks if a property has a value different than null
     * 
     * @param string $name
     */
    public function __isset($name)
    {
        return isset($this->_sourceObject->{$name});
    }

    /**
     * Unsets a property of the source object
     * 
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->_sourceObject->{$name});
    }

}