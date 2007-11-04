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
class PHPSpec_Runner_Result implements Countable
{

    protected $_examples = array();

    protected $_failCount = 0;

    protected $_passCount = 0;

    protected $_exceptionCount = 0;

    protected $_errorCount = 0;
    
    protected $_pendingCount = 0;

    protected $_specCount = 0;

    public function execute(PHPSpec_Runner_Collection $collection)
    {
        $collection->execute($this);
    }

    public function addFailure(PHPSpec_Runner_Example $example)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Fail($example);
        $this->_failCount++;
    }

    public function addException(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Exception($example, $e);
        $this->_exceptionCount++;
    }

    public function addError(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Error($example, $e);
        $this->_errorCount++;
    }
    
    public function addPending(PHPSpec_Runner_Example $example, Exception $e)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Pending($example, $e);
        $this->_pendingCount++;
    }

    public function addPass(PHPSpec_Runner_Example $example)
    {
        $this->_examples[] = new PHPSpec_Runner_Example_Pass($example);
        $this->_passCount++;
    }

    public function getTypes($type)
    {
        $class = 'PHPSpec_Runner_Example_' . ucfirst($type);
        $types = array();
        foreach ($this->_examples as $example) {
            if ($example instanceof $class) {
                $types[] = $example;
            }
        }
        return $types;
    }

    public function getPasses()
    {
        $passes = array();
        foreach ($this->_examples as $example) {
            if ($example instanceof PHPSpec_Runner_Example_Pass) {
                $passes[] = $example;
            }
        }
        return $passes;
    }

    public function getFailures()
    {
        $fails = array();
        foreach ($this->_examples as $example) {
            if ($example instanceof PHPSpec_Runner_Example_Fail) {
                $fails[] = $example;
            }
        }
        return $fails;
    }

    public function getExceptions()
    {
        $exceptions = array();
        foreach ($this->_examples as $example) {
            if ($example instanceof PHPSpec_Runner_Example_Exception) {
                $exceptions[] = $example;
            }
        }
        return $exceptions;
    }

    public function getErrors()
    {
        $errors = array();
        foreach ($this->_examples as $example) {
            if ($example instanceof PHPSpec_Runner_Example_Error) {
                $errors[] = $example;
            }
        }
        return $errors;
    }
    
    public function getPending()
    {
        $pending = array();
        foreach ($this->_examples as $example) {
            if ($example instanceof PHPSpec_Runner_Example_Pending) {
                $pending[] = $example;
            }
        }
        return $pending;
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

    public function count()
    {
        return $this->getSpecCount();
    }

    public function countPasses()
    {
        return $this->_passCount;
    }

    public function countFails()
    {
        return $this->_failCount;
    }

    public function countExceptions()
    {
        return $this->_exceptionCount;
    }

    public function countErrors()
    {
        return $this->_errorCount;
    }

}