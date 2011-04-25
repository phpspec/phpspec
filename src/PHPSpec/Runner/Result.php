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
class Result implements \Countable
{

    /**
     * The examples being ran
     * 
     * @var array
     */
    protected $_examples = array();

    /**
     * The number of fails
     * 
     * @var integer
     */
    protected $_failCount = 0;

    /**
     * The number of pass
     * 
     * @var integer
     */
    protected $_passCount = 0;

    /**
     * The number of exceptions
     * 
     * @var integer
     */
    protected $_exceptionCount = 0;

    /**
     * The number of errors
     * 
     * @var integer
     */
    protected $_errorCount = 0;
    
    /**
     * The number of pending specs
     * 
     * @var integer
     */
    protected $_pendingCount = 0;

    /**
     * The number of deliberate fails 
     *
     * @var integer
     */
    protected $_deliberateFailCount = 0;

    /**
     * The number of specs
     * 
     * @var integer
     */
    protected $_specCount = 0;
    
    /**
     * The reporter being used
     * 
     * @var \PHPSpec\Runner\Reporter
     */
    protected $_reporter = null;
    
    /**
     * Time at start
     * 
     * @var float
     */
    protected $_runtimeStart = 0;
    
    /**
     * Time at end
     * 
     * @var float
     */
    protected $_runtimeEnd = 0;

    /**
     * Executes the collection
     * 
     * @param Collection $collection
     */
    public function execute(Collection $collection)
    {
        $collection->execute($this);
    }

    /**
     * Adds a fail, increases the count and outputs the status
     * 
     * @param Example $example
     * @param string  $line
     */
    public function addFailure(Example $example, $line)
    {
        $fail = new Example\Fail($example);
        $fail->setLine($line);
        $this->_examples[] = $fail;
        $this->_failCount++;
        $this->_reporter->outputStatus('F');
    }

    /**
     * Adds a deliberate fail, increases the count and outputs the status
     * 
     * @param Example $example
     * @param \Exception $e
     */
    public function addDeliberateFailure(Example $example, \Exception $e)
    {
        $this->_examples[] = new Example\DeliberateFail($example, $e);
        $this->_deliberateFailCount++;
        $this->_reporter->outputStatus('F');
    }

    /**
     * Adds an exception, increases the count and outputs the status
     * 
     * @param Example $example
     * @param \Exception $e
     */
    public function addException(Example $example, \Exception $e)
    {
        $this->_examples[] = new Example\Exception($example, $e);
        $this->_exceptionCount++;
        $this->_reporter->outputStatus('E');
    }

    /**
     * Adds an error, increases the count and outputs the status
     * 
     * @param Example $example
     * @param \Exception $e
     */
    public function addError(Example $example, \Exception $e)
    {
        $this->_examples[] = new Example\Error($example, $e);
        $this->_errorCount++;
        $this->_reporter->outputStatus('E');
    }
    
    /**
     * Adds a pending, increases the count and outputs the status
     * 
     * @param Example $example
     * @param \Exception $e
     */
    public function addPending(Example $example, \Exception $e)
    {
        $this->_examples[] = new Example\Pending($example, $e);
        $this->_pendingCount++;
        $this->_reporter->outputStatus('P');
    }

    /**
     * Adds a pass, increases the count and outputs the status
     * 
     * @param Example $example
     */
    public function addPass(Example $example)
    {
        $this->_examples[] = new Example\Pass($example);
        $this->_passCount++;
        $this->_reporter->outputStatus('.');
    }

    /**
     * Get an array of Example types which contain the specific Example
     * objects to collate any other details needed for reporting or
     * even re-performance.
     * 
     * @todo Needs refactoring to remove these duplicate methods!
     * @param string $type The \PHPSpec\Runner\Example type being declared
     * @return array Array of all examples of this specific type
     */
    public function getTypes($type)
    {
        $class = "Example\\" . ucfirst($type);
        $types = array();
        foreach ($this->_examples as $example) {
            if ($class == "Example\\Exception") {
                if ($example instanceof $class
                    && !$example instanceof Example\Error
                    && !$example instanceof Example\Pending
                    && !$example instanceof Example\DeliberateFail) {
                    $types[] = $example;
                }
            } elseif ($example instanceof $class) {
                $types[] = $example;
            }
        }
        return $types;
    }
    
    /**
     * Sets the reporter
     * 
     * @param Reporter $reporter
     */
    public function setReporter(Reporter $reporter)
    {
        $this->_reporter = $reporter;
    }
    

    /**
     * Increments spec count
     * 
     * @param integer $count
     */
    public function addSpecCount($count = 1)
    {
        $this->_specCount += intval($count);
    }

    /**
     * Sets Spec count
     * 
     * @param integer $count
     */
    public function setSpecCount($count)
    {
        $this->_specCount = intval($count);
    }

    /**
     * Gets spec count
     * 
     * @return integer
     */
    public function getSpecCount()
    {
        return $this->_specCount;
    }

    /**
     * Gets the examples
     * 
     * @return array
     */
    public function getExamples()
    {
        return $this->_examples;
    }

    /**
     * Returns the number of specs
     * @see \Countable::count()
     * 
     * @return integer
     */
    public function count()
    {
        return $this->getSpecCount();
    }

    /**
     * Returns the number of passing examples
     * 
     * @return integer
     */
    public function countPasses()
    {
        return $this->_passCount;
    }

    /**
     * Returns the number of failing examples
     * 
     * @return integer
     */
    public function countFailures()
    {
        return $this->_failCount;
    }

    /**
     * Returns the number of deliberate failing examples
     * 
     * @return integer
     */
    public function countDeliberateFailures()
    {
        return $this->_deliberateFailCount;
    }

    /**
     * Returns the number of examples with exceptions
     * 
     * @return integer
     */
    public function countExceptions()
    {
        return $this->_exceptionCount;
    }

    /**
     * Returns the number of examples with errors
     * 
     * @return integer
     */
    public function countErrors()
    {
        return $this->_errorCount;
    }
    
    /**
     * Returns the number of pending examples
     * 
     * @return integer
     */
    public function countPending()
    {
        return $this->_pendingCount;
    }
    
    /**
     * Sets the start time
     * 
     * @param float $microtime
     */
    public function setRuntimeStart($microtime)
    {
        $this->_runtimeStart = $microtime;
    }
    
    /**
     * Sets the end time
     * 
     * @param float $microtime
     */
    public function setRuntimeEnd($microtime)
    {
        $this->_runtimeEnd = $microtime;
    }
    
    /**
     * Returns the runtime
     * 
     * @return string
     */
    public function getRuntime()
    {
        return sprintf("%.6F", $this->_runtimeEnd - $this->_runtimeStart);
    }

}