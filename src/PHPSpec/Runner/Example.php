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
namespace PHPSpec\Runner;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Example
{

    /**
     * The context being ran
     * 
     * @var \PHPSpec\Context
     */
    protected $_context;
    
    /**
     * The name of the example taken from the method name
     * 
     * @var string
     */
    protected $_methodName;
    
    /**
     * The specification text
     * 
     * @var string
     */
    protected $_specificationText;
    
    /**
     * @var \PHPSpec\Specification
     */
    protected $_specBeingExecuted;
    
    /**
     * The failure message
     * 
     * @var string
     */
    protected $_failedMessage;

    /**
     * Example is constructed with the context and method name
     * 
     * @param \PHPSpec\Context $context
     * @param string $methodName
     */
    public function __construct(\PHPSpec\Context $context, $methodName)
    {
        $this->_context = $context;
        $this->_methodName = $methodName;
        $this->_specificationText = $this->_setSpecificationText(
            $this->_methodName
        );
    }

    /**
     * Gets the method name
     * 
     * @return string
     */
    public function getMethodName()
    {
        return $this->_methodName;
    }

    /**
     * Returns the specification as text
     * 
     * @return string
     */
    public function getSpecificationText()
    {
        return $this->_specificationText;
    }

    /**
     * Returns the context description
     * 
     * @return string
     */
    public function getContextDescription()
    {
        return $this->_context->getDescription();
    }

    /**
     * Returns the specificantion being ran
     * 
     * @return \PHPSpec\Specification
     * @throws \PHPSpec\Exception
     */
    public function getSpecificationBeingExecuted()
    {
        if (is_null($this->_specBeingExecuted)) {
            throw new \PHPSpec\Exception(
                "cannot return a \\PHPSpec\\Specification ".
                "until the example is executed"
            );
        }
        return $this->_specBeingExecuted;
    }

    /**
     * Returns a failure message
     * 
     * @return string
     * @throws \PHPSpec\Exception
     */
    public function getFailedMessage()
    {
        if (is_null($this->_failedMessage)) {
            throw new \PHPSpec\Exception(
                'cannot return a failure message until the example is executed'
            );
        }
        return $this->_failedMessage;
    }

    /**
     * Executes the example
     * 
     * @throws FailedMatcherException
     */
    public function execute()
    {
        $this->_context->clearCurrentSpecification();

        /**
         * Spec execution
         * *Each methods are reserved for internal stepping setup/teardown
         */
        if (method_exists($this->_context, 'beforeEach')) {
            $this->_context->beforeEach();
        }
        if (method_exists($this->_context, 'before')) {
            $this->_context->before();
        }
        
        $line = '';
        try {
            $this->_context->{$this->_methodName}();
        } catch (FailedMatcherException $e) {
            $line = $e->getFormattedLine();
        }

        if (method_exists($this->_context, 'after')) {
            $this->_context->after();
        }
        if (method_exists($this->_context, 'afterEach')) {
            $this->_context->afterEach();
        }

        /**
         * Result collection
         */
        $this->_specBeingExecuted = $this->_context
                                         ->getCurrentSpecification();
        if (is_null($this->_specBeingExecuted)) {
            return;
        }

        $expected = $this->_specBeingExecuted
                         ->getExpectation()
                         ->getExpectedMatcherResult();
        $actual = $this->_specBeingExecuted->getMatcherResult();
        if ($expected !== $actual) { // ===
            if ($expected === true) {
                $this->_failedMessage = $this->_specBeingExecuted
                                             ->getMatcherFailureMessage();
            } else {
                $this->_failedMessage =
                    $this->_specBeingExecuted
                         ->getMatcherNegativeFailureMessage();
            }
            
            $e->setFormattedLine($line);
            throw $e; // add spec data later
        }
    }

    /**
     * Sets the specification text taken from method name
     * 
     * @param string $methodName
     * @return string
     */
    protected function _setSpecificationText($methodName)
    {
        $methodName = substr($methodName, 2);
        $terms = preg_split(
            "/(?=[[:upper:]])/", $methodName, -1, PREG_SPLIT_NO_EMPTY
        );
        $termsLowercase = array_map('strtolower', $terms);
        return implode(' ', $termsLowercase);
    }
}
