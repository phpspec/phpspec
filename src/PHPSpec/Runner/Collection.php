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
class PHPSpec_Runner_Collection implements Countable
{

    protected $_context = null;
    protected $_examples = array();
    protected $_description = null;
    protected $_exampleClass = 'PHPSpec_Runner_Example';

    public function __construct(PHPSpec_Context $context, $exampleClass = null)
    {
        $this->_context = $context;
        if (!is_null($exampleClass)) {
            $this->_verifyExampleClass($exampleClass);
            $this->_exampleClass = strval($exampleClass);
        }
        $this->_buildExamples();
        $this->_description = $context->getDescription();
    }

    public function getExamples()
    {
        return $this->_examples;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function count()
    {
        return count($this->_examples);
    }

    public function execute(PHPSpec_Runner_Result $result)
    {
        set_error_handler('PHPSpec_ErrorHandler');

        if (method_exists($this->_context, 'beforeAll')) {
            $this->_context->beforeAll();
        }

        $examples = $this->getExamples();
        foreach ($examples as $example) {
            $result->addSpecCount();
            try {
                $example->execute();
                $result->addPass($example);
            } catch (PHPSpec_Runner_FailedMatcherException $e) {
                $result->addFailure($example);
            } catch (PHPSpec_Runner_ErrorException $e) {
                $result->addError($example, $e);
            } catch (PHPSpec_Runner_PendingException $e) {
                $result->addPending($example, $e);
            } catch (PHPSpec_Runner_DeliberateFailException $e) {
                $result->addDeliberateFailure($example, $e);
            } catch (Exception $e) {
                $result->addException($example, $e);
            }
        }

        if (method_exists($this->_context, 'afterAll')) {
            $this->_context->afterAll();
        }

        restore_error_handler();
    }

    protected function _buildExamples()
    {
        $methods = $this->_context->getSpecMethods();
        foreach ($methods as $methodName) {
            $this->_addExample( new $this->_exampleClass($this->_context, $methodName) );
        }
    }

    protected function _addExample(PHPSpec_Runner_Example $example)
    {
        $this->_examples[] = $example;
    }

    protected function _verifyExampleClass($exampleClass)
    {
        $class = new ReflectionClass($exampleClass);
        if (!$class->isSubclassOf(new ReflectionClass('PHPSpec_Runner_Example'))) {
            throw new PHPSpec_Exception('not a valid PHPSpec_Runner_Example subclass');
        }
    }
}