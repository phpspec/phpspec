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
namespace PHPSpec\Specification;

use \PHPSpec\Specification\Result\Failure,
    \PHPSpec\Specification\Result\Error,
    \PHPSpec\Specification\Interceptor\InterceptorFactory;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
abstract class Interceptor
{
    /**
     * Expectation forces matcher result to fail if it returns false
     */
    const SHOULD = 'should';
    
    /**
     * Expectation forces matcher result to fail if it returns true
     */
    const SHOULD_NOT = 'should not';
    
    /**
     * The actual value
     *
     * @var mixed
     */
    protected $_actualValue;
    
    /**
     * The expectation
     *
     * @var mixed
     */
    protected $_expectation;
    
    /**
     * The expected value
     *
     * @var mixed
     */
    protected $_expectedValue;
    
    /**
     * List of valid matchers
     * 
     * @var array
     */
    protected $_matchers = array(
        'be', 'beAnInstanceOf', 'beEmpty', 'beEqualTo', 'beFalse',
        'beGreaterThan', 'beGreaterThanOrEqualTo', 'beInteger',
        'beLessThan', 'beLessThanOrEqualTo', 'beNull', 'beString', 'beTrue',
        'equal', 'match', 'throwException'
    );
    
    /**
     * Creates an interceptor with the intercepted actual value
     * 
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->_actualValue = $value;
    }
    
    /**
     * Checks whether a expectation is being invoked
     * 
     * @param string $attribute
     * 
     * @return \PHPSpec\Specification\Interceptor|mixed
     */
    public function __get($attribute)
    {
        switch ($attribute) {
            case 'should' :
                $this->_expectation = self::SHOULD;
                return $this;
            case 'shouldNot' :
                $this->_expectation = self::SHOULD_NOT;
                return $this;
            default :
                if (method_exists($this->_actualValue, '__get')) {
                    $parentInterceptor = new \ReflectionMethod(
                        $this->_actualValue, '__get'
                    );
                    return $parentInterceptor->invokeArgs(
                        $this->_actualValue, array($attribute)
                    );
                }
        }
    }
    
    /**
     * Invokes a matcher
     * 
     * @param string $method
     * @param array $args
     * @return boolean|mixed
     */
    public function __call($method, $args)
    {
        if (in_array($method, $this->_matchers)) {
            $this->setExpectedValue($args);
            $this->createMatcher($method);
            $this->performMatching();
            return true;
        }
        
        if (\PHPSpec\Matcher\MatcherRepository::has($method)) {
            $this->setExpectedValue($args);
            $expected = !is_array($this->getExpectedValue()) ?
                        array($this->getExpectedValue()) :
                        $this->getExpectedValue();
            $this->_matcher = new \PHPSpec\Matcher\UserDefined(
                $method, $expected
            );
            $this->performMatching();
            return true;
        }
        
        if (method_exists($this->_actualValue, '__call')) {
            $parentInterceptor = new \ReflectionMethod(
                $this->_actualValue, '__call'
            );
            return $parentInterceptor->invokeArgs(
                $this->_actualValue, $args
            );
        }
        
        if (!$this instanceof Interceptor\Object &&
            $this->_expectation !== null) {
            throw new \BadMethodCallException(
                "Call to undefined method $method"
            );
        }
    }
    
    /**
     * Sets an Expected value with which to instantiate any new Matcher
     *
     * @param mixed $value
     * @return null
     */
    public function setExpectedValue($value)
    {
        $this->_expectedValue = $value;
    }
    
    /**
     * Gets an Expected value with which to instantiate any new Matcher
     *
     * @return mixed
     */
    public function getExpectedValue()
    {
        return $this->_expectedValue;
    }
    
    /**
     * Sets the Actual value
     *
     * @param mixed $value
     * @return null
     */
    public function setActualValue($value)
    {
        $this->_actualValue = $value;
    }
    
    /**
     * Gets an Expected value with which to instantiate any new Matcher
     *
     * @return mixed
     */
    public function getActualValue()
    {
        return $this->_actualValue;
    }
    
    /**
     * Returns the Expectation ruling how Matcher results are
     * interpreted (whether a matcher boolean result counts as a pass
     * or a fail).
     *
     * @return string (self::SHOULD | self::SHOULD_NOT)
     */
    public function getExpectation()
    {
        return $this->_expectation;
    }
    
    /**
     * Adds a matcher
     * 
     * @param string $matcher
     */
    public function addMatcher($matcher)
    {
        $this->_matchers[] = $matcher;
    }
    
    /**
     * Adds many matcher at once
     * 
     * @param array $matchers
     */
    public function addMatchers(array $matchers)
    {
        $this->_matchers = array_merge($this->_matchers, $matchers);
    }
    
    /**
     * Sets the matchers. Replaces existing ones (!)
     *
     * @param array $matchers
     */
    public function setMatchers(array $matchers)
    {
        $this->_matchers = $matchers;
    }
    
    /**
     * Creates a new Matcher object based on calls
     * to the DSL grammer. (factory)
     *
     * @param DSL method call which was found to be a Matcher reference
     */
    protected function createMatcher($matcher)
    {
        $matcher = strtoupper($matcher[0]) . substr($matcher, 1);
        $expected = $this->assertExpectedIsArray();
        
        try {
            $matcherClass = '\PHPSpec\Matcher\\' . $matcher;
            $reflectedMatcher = new \ReflectionClass($matcherClass);
            $this->_matcher = $reflectedMatcher->newInstanceArgs($expected);
        } catch (\ReflectionException $e) {
            try {
                $matcherClass = '\PHPSpec\Context\Zend\Matcher\\' . $matcher;
                $reflectedMatcher = new \ReflectionClass($matcherClass);
                $this->_matcher = $reflectedMatcher->newInstanceArgs(
                    $expected
                );
            } catch(\ReflectionException $e) {
                throw new \PHPSpec\Exception("Could not find matcher $matcher");
            }
        }
        
    }
    
    /**
     * Asserts the expected value is in an array
     * 
     * !FIXME this is only being used because the way Closure specification
     * and throwException matcher work
     * 
     * @return array
     */
    protected function assertExpectedIsArray()
    {
        return !is_array($this->getExpectedValue()) ?
               array($this->getExpectedValue()) :
               $this->getExpectedValue();
    }
    
    /**
     * Performs a Matcher operation and set the returned boolean result for
     * further analysis, e.g. comparison to the boolean Expectation.
     *
     * @param array $args
     * @return null
     */
    protected function performMatching()
    {
        $actual = $this->getActualValue();

        if (is_array($actual) && $this->_composedActual) {
            $args = $actual;
        } else {
            $args = array($actual);
        }

        $result = call_user_func_array(
            array($this->_matcher, 'matches'), $args
        );
        
        if ($this->getExpectation() === self::SHOULD) {
            if ($result === false) {
                throw new Failure($this->_matcher->getFailureMessage());
            }
        } elseif ($this->getExpectation() === self::SHOULD_NOT) {
            if ($result === true) {
                throw new Failure($this->_matcher->getNegativeFailureMessage());
            }
        } elseif (empty($this->_expectation)) {
            throw new Error(
                'Missing expectation "should" or "shouldNot". ' .
                'Make sure you use them as properties and not as methods.'
            );
        }
    }
}