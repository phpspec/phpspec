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
 * @copyright Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Specification\Interceptor;

use PHPSpec\Specification\Interceptor,
    PHPSpec\Matcher\InvalidMatcher;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Object extends Interceptor
{
    /**
     * Accepted predicantes
     *
     * @var array
     */
    protected $_predicate = array('be' => 'is', 'have' => 'has');
    
    /**
     * Proxies call to specification and if method is a dsl call than it calls
     * the interceptor factory for the returned value
     * 
     * @param string $method
     * @param array $args
     * @return \PHPSpec\Specification\Interceptor|boolean
     */
    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }
        
        if ($this->isPredicate('have', $method, $args) ||
            $this->isPredicate('be', $method, $args)) {
            $this->performMatching();
            return true;
        }
        
        $object = $this->getActualValue();
        if (method_exists($object, $method)) {
            return InterceptorFactory::create(
                call_user_func_array(array($object, $method), $args)
            );
        }
        
        if ($method === 'property') {
            return $this->accessProperty($args[0]);
        }
        
        $class = get_class($object);
        throw new InvalidMatcher(
            "Call to undefined method {$class}::{$method}"
        );
    }
    
    /**
     * Checks whether a method is a predicate
     *
     * @param string $type 
     * @param string $method 
     * @param array  $args 
     * @return boolean
     */
    protected function isPredicate($type, $method, $args)
    {
        if (!in_array($type, array_keys($this->_predicate))) {
            return false;
        }
        if (strpos($method, $type) !== 0) {
            return false;
        }
        
        $plain = $this->_predicate[$type] . substr($method, strlen($type));
        $a = $this->_predicate[$type] . substr($method, strlen($type) + 1);
        $an = $this->_predicate[$type] . substr($method, strlen($type) + 2);
        
        switch (true) {
            case method_exists($this->_actualValue, $plain) :
                $predicate = $plain;
                break;
            case method_exists($this->_actualValue, $a) :
                if (strtolower(substr($method, strlen($type), 1)) !== 'a') {
                    return false;
                }
                $predicate = $a;
                break;
            case method_exists($this->_actualValue, $an) :
                if (strtolower(substr($method, strlen($type), 2)) !== 'an') {
                    return false;
                }
                $predicate = $an;
                break;
            default:
                return false;
        }
        
        $this->setExpectedValue($args);
        $this->_matcher = $this->getMatcherFactory()->create('beTrue', array(true));
        $this->setActualValue(
            call_user_func_array(array($this->_actualValue, $predicate), $args)
        );
        return true;
    }
    
    /**
     * Proxies call to specification and if argument is a dsl call than it calls
     * the interceptor factory for the returned value
     *
     * @param string $attribute 
     * @return mixed
     */
    public function __get($attribute)
    {
        $dslResult = parent::__get($attribute);
        if (!is_null($dslResult)) {
            return $dslResult;
        }
        
        if (isset($this->getActualValue()->$attribute)) {
            return InterceptorFactory::create(
                $this->getActualValue()->$attribute
            );
        }
        
        trigger_error(
            "Undefined property: " . get_class($this->getActualValue()) .
            "::$attribute", E_USER_NOTICE
        );
    }

    /**
     * Access the value of a unaccessible property and returns the
     * intercepted value
     *
     * @param string $property 
     * @return \PHPSpec\Specification\Interceptor 
     */
    private function accessProperty($property)
    {
        $classes = $this->getClassAndParents();
        $objectAsArray = (array)$this->getActualValue();
        $protected = "\0*\0" . $property;
        
        if (array_key_exists($protected, $objectAsArray)) {
            return InterceptorFactory::create($objectAsArray[$protected]);
        }
        
        foreach ($classes as $class) {
            $private = sprintf("\0%s\0%s", $class, $property);
            
            if (array_key_exists($private, $objectAsArray)) {
                return InterceptorFactory::create($objectAsArray[$private]);
            }
        }
        
        $class = get_class($this->getActualValue());
        trigger_error(
            "Undefined property: $class::\$$property", E_USER_NOTICE
        );
    }
    
    /**
     * Scans all super classes of a class and returns them in an array
     *
     * @return array 
     */
    private function getClassAndParents()
    {
        $parents = array();
        
        $class = new \ReflectionClass($this->getActualValue());
        $classes[] = $class->getName();
        
        while ($class = $class->getParentClass()) {
            $classes[] = $class->getName();
        }
        return $classes;
    }
}