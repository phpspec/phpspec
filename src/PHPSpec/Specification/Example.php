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

use \PHPSpec\Runner\Reporter,
    \PHPSpec\Specification\Result\Exception,
    \PHPSpec\Specification\Result\Error,
    \PHPSpec\Specification\Result\Pending,
    \PHPSpec\Specification\Result\DeliberateFailure,
    \PHPSpec\Specification\Result\Failure,
    \PHPSpec\Util\Filter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Example
{
    /**
     * The example method name
     *
     * @var string
     */
    protected $_methodName;
    /**
     * The example group
     *
     * @var PHPSpec\Specification\ExampleGroup
     */
    protected $_exampleGroup;
    /**
     * Represents the execution time of the example
     * 
     * @var integer
     */
    protected $_executionTime;
    /**
     * Example keeps a reference to the example group and is created with the
     * example as a reflected method
     * 
     * @param PHPSpec\Specification\ExampleGroup $exampleGroup
     * @param string                             $methodName
     */
    public function __construct (ExampleGroup $exampleGroup, $methodName)
    {
        $this->_methodName = $methodName;
        $this->_exampleGroup = $exampleGroup;
    }
    /**
     * Runs the example
     * 
     * @param \PHPSpec\Runner\Reporter $reporter
     */
    public function run (Reporter $reporter)
    {
        try {
            $methodName = $this->_methodName;
            $startTime = microtime(true);
            call_user_func(array($this->_exampleGroup, 'before'));
            call_user_func(array($this->_exampleGroup, $methodName));
            call_user_func(array($this->_exampleGroup, 'after'));
            $endTime = microtime(true);
            $this->_executionTime = $endTime - $startTime;
            if (class_exists('Mockery')) {
                \Mockery::close();
            }
        } catch (Failure $failure) {
            $reporter->addFailure($this, $failure);
            return;
        } catch (Pending $pending) {
            $reporter->addPending($this, $pending);
            return;
        } catch (Error $error) {
            $reporter->addError($this, $error);
            return;
        } catch (\Exception $e) {
            $reporter->addException($this, new Exception($e));
            return;
        }
        $reporter->addPass($this);
    }
    /**
     * Gets the description in the following format:
     * 
     * DescribeStringCalculator::itReturnZeroWithNoArguments
     * becomes
     * StringCalculator returns zero with no argument
     * 
     * @return string
     */
    public function getDescription ()
    {
        $class = str_replace('Describe', '', get_class($this->_exampleGroup));
        return "$class " . $this->getSpecificationText();
    }
    /**
     * Return the specification text taken from method name
     * 
     * itReturnZeroWithNoArguments
     * becomes
     * returns zero with no argument
     * 
     * @param string $methodName
     * @return string
     */
    public function getSpecificationText ()
    {
        $methodName = substr($this->_methodName, 2);
        return Filter::camelCaseToSpace($methodName);
    }
    /**
     * Returns the method name of the testcase. This method is used in the
     * junit formatter.
     *
     * @return string
     */
    public function getMethodName ()
    {
        return $this->_methodName;
    }
    /**
     * Returns the example group. This method is used in the junit formatter.
     * 
     * @return ExampleGroup|PHPSpec\Specification\ExampleGroup
     */
    public function getExampleGroup ()
    {
        return $this->_exampleGroup;
    }
    /**
     * Returns the execution time for this example.
     *
     * @return float
     */
    public function getExecutionTime ()
    {
        return $this->_executionTime;
    }
}