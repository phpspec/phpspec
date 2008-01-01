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
 * @todo       Needs a suite of updated tests, esp. addressing output being traced out
 */
class PHPSpec_Runner_Result implements Countable
{

    protected $_examples = array();

    protected $_failCount = 0;

    protected $_passCount = 0;

    protected $_exceptionCount = 0;

    protected $_errorCount = 0;
    
    protected $_pendingCount = 0;

    protected $_deliberateFailCount = 0;

    protected $_specCount = 0;
    
    protected $_reporter = null;
    
    protected $_runtimeStart = 0;
    
    protected $_runtimeEnd = 0;

    public function execute(PHPSpec_Runner_Collection $collection)
    {
        $collection->execute($this);
    }

    public function addFailure(PHPSpec_Runner_Example $example)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Fail($example);
        $this->_failCount++;
        $this->_reporter->outputStatus('F');
    }

    public function addDeliberateFailure(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_DeliberateFail($example, $e);
        $this->_deliberateFailCount++;
        $this->_reporter->outputStatus('F');
    }

    public function addException(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Exception($example, $e);
        $this->_exceptionCount++;
        $this->_reporter->outputStatus('E');
    }

    public function addError(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Error($example, $e);
        $this->_errorCount++;
        $this->_reporter->outputStatus('E');
    }
    
    public function addPending(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Pending($example, $e);
        $this->_pendingCount++;
        $this->_reporter->outputStatus('P');
    }

    public function addPass(PHPSpec_Runner_Example $example)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Pass($example);
        $this->_passCount++;
        $this->_reporter->outputStatus('.');
    }

    /**
     * Get an array of Example types which contain the specific Example
     * objects to collate any other details needed for reporting or
     * even re-performance.
     * 
     * @todo Needs refactoring to remove these duplicate methods!
     * @param string $type The PHPSpec_Runner_Example type being declared
     * @return array Array of all examples of this specific type
     */
    public function getTypes($type)
    {
        $class = 'PHPSpec_Runner_Example_' . ucfirst($type);
        $types = array();
        foreach ($this->_examples as $example) {
            if ($class == 'PHPSpec_Runner_Example_Exception') {
                if ($example instanceof $class
                    && !$example instanceof PHPSpec_Runner_Example_Error
                    && !$example instanceof PHPSpec_Runner_Example_Pending
                    && !$example instanceof PHPSpec_Runner_Example_DeliberateFail) {
                    $types[] = $example;
                }
            } elseif ($example instanceof $class) {
                $types[] = $example;
            }
        }
        return $types;
    }
    
    public function setReporter(PHPSpec_Runner_Reporter $reporter)
    {
    	$this->_reporter = $reporter;
    }
    

    public function addSpecCount($count = 1)
    {
        $this->_specCount += intval($count);
    }

    public function setSpecCount($count)
    {
        $this->_specCount = intval($count);
    }

    public function getSpecCount()
    {
        return $this->_specCount;
    }

    public function getExamples()
    {
        return $this->_examples;
    }

    public function count()
    {
        return $this->getSpecCount();
    }

    public function countPasses()
    {
        return $this->_passCount;
    }

    public function countFailures()
    {
        return $this->_failCount;
    }

    public function countDeliberateFailures()
    {
        return $this->_deliberateFailCount;
    }

    public function countExceptions()
    {
        return $this->_exceptionCount;
    }

    public function countErrors()
    {
        return $this->_errorCount;
    }
    
    public function countPending()
    {
        return $this->_pendingCount;
    }
    
    public function setRuntimeStart($microtime) {
        $this->_runtimeStart = $microtime;
    }
    
    public function setRuntimeEnd($microtime) {
        $this->_runtimeEnd = $microtime;
    }
    
    public function getRuntime() {
        return ($this->_runtimeEnd - $this->_runtimeStart);
    }

}