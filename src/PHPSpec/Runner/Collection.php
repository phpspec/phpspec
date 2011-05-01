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
class Collection implements \Countable
{

    /**
     * Context of the examples
     * 
     * @var \PHPSpec\Context
     */
    protected $_context = null;

    /**
     * Examples in the collection
     * 
     * @var array
     */
    protected $_examples = array();
    
    /**
     * @var string
     */
    protected $_description = null;

    /**
     * Constructs the collection with the context object
     * 
     * @param \PHPSpec\Context $context
     * @param string           $exampleClass
     */
    public function __construct(\PHPSpec\Context $context)
    {
        $this->_context = $context;
        $this->_buildExamples();
        $this->_description = $context->getDescription();
    }

    /**
     * Returns the collection's examples
     * 
     * @return array
     */
    public function getExamples()
    {
        return $this->_examples;
    }

    /**
     * Returns the description
     * 
     * @return strings
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Returns the number of examples in the collection
     * @see Countable::count()
     * 
     * @return interger
     */
    public function count()
    {
        return count($this->_examples);
    }

    /**
     * Executes the collection
     * 
     * @param Result $result
     */
    public function execute(Result $result)
    {
        set_error_handler("PHPSpec_ErrorHandler");

        if (method_exists($this->_context, 'beforeAll')) {
            $this->_context->beforeAll();
        }

        $examples = $this->getExamples();
        foreach ($examples as $example) {
            $result->addSpecCount();
            try {
                if (method_exists($this->_context, 'before')) {
                    $this->_context->before();
                }
                $example->execute();
                $result->addPass($example);
                if (method_exists($this->_context, 'after')) {
                    $this->_context->after();
                }
            } catch (FailedMatcherException $e) {
                $result->addFailure($example, $e->getFormattedLine());
            } catch (ErrorException $e) {
                $result->addError($example, $e);
            } catch (PendingException $e) {
                $result->addPending($example, $e);
            } catch (DeliberateFailException $e) {
                $result->addDeliberateFailure($example, $e);
            } catch (\Exception $e) {
                $result->addException($example, $e);
            }
        }

        if (method_exists($this->_context, 'afterAll')) {
            $this->_context->afterAll();
        }

        restore_error_handler();
    }

    /**
     * Initiates the example structure by extracting the examples from the
     * current context
     */
    protected function _buildExamples()
    {
        $methods = $this->_context->getSpecMethods();
        foreach ($methods as $methodName) {
            $this->_addExample(
                new \PHPSpec\Runner\Example($this->_context, $methodName)
            );
        }
    }

    /**
     * Adds an example to the {@link self::$examples} property
     * 
     * @param \PHPSpec\Runner\Example $example
     */
    protected function _addExample(Example $example)
    {
        $this->_examples[] = $example;
    }

    /**
     * Validates that a given example class extends \PHPSpec\Runner\Example
     * 
     * @param string $exampleClass
     * @throws \PHPSpec\Exception
     */
    protected function _verifyExampleClass($exampleClass)
    {
        $class = new \ReflectionClass($exampleClass);
        if (!$class->isSubclassOf(
            new \ReflectionClass('\PHPSpec\Runner\Example')
        )) {
            throw new \PHPSpec\Exception(
                'not a valid \PHPSpec\Runner\Example subclass'
            );
        }
    }
}