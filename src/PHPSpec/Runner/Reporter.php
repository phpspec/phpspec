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

use \PHPSpec\Runner\Formatter,
    \PHPSpec\Specification\Result\Failure,
    \PHPSpec\Specification\Result\Error,
    \PHPSpec\Specification\Result\Exception,
    \PHPSpec\Specification\Result\Pending,
    \PHPSpec\Specification\Result\DeliberateFailure,
    \PHPSpec\Specification\Example,
    \PHPSpec\Specification\ExampleGroup;

abstract class Reporter implements \SPLSubject
{
    protected $_formatters;
    protected $_failures;
    protected $_errors;
    protected $_pendingExamples;
    protected $_exceptions;
    protected $_startTime;
    protected $_endTime;
    protected $_passing = array();
    
    abstract public function setMessage($string);
    abstract public function hasMessage();
    abstract public function getMessage();
    abstract public function getFormatter();
    abstract public function addDeliberateFailure(Example $example,
                                                  DeliberateFailure $failure);
    abstract public function addFailure(Example $example, Failure $failure);
    abstract public function addError(Example $example, Error $error);
    abstract public function addException(Example $example, \Exception $e);
    abstract public function addPending(Example $example, Pending $pending);
    abstract public function addPass(Example $example);
    
    public function __construct()
    {
        $this->_failures           = new \SplObjectStorage;
        $this->_errors             = new \SplObjectStorage;
        $this->_pendingExamples    = new \SplObjectStorage;
        $this->_exceptions         = new \SplObjectStorage;
    }
    
    
    public function attach(\SPLObserver $formatter)
    {
        $this->_formatters[] = $formatter;
    }
    
    public function detach(\SPLObserver $formatter)
    {
        $remainingObservers = array();
        foreach ($this->_formatters as $observer) {
            if ($observer === $formatter) {
                continue;
            }
            $remainingObservers[] = $formatter;
        }
        $this->formatters = $remainingObservers;
    }
    
    public function exampleGroupStarted(ExampleGroup $exampleGroup)
    {
        $name = preg_replace('/Describe(?!.*Describe)/', '', get_class($exampleGroup));
        $time = microtime(true);
        $this->notify('start', $time, $name);
    }
    
    public function exampleGroupFinished(ExampleGroup $exampleGroup)
    {
        $name = preg_replace('/Describe(?!.*Describe)/', '', get_class($exampleGroup));
        $time = microtime(true);
        $this->notify('finish', $time, $name);
    }
    
    public function notify()
    {
        foreach ($this->_formatters as $observer) {
            $observer->update($this, func_get_args());
        }
    }
    
    public function setRuntimeStart($time = null)
    {
        $this->_startTime = $time ?: microtime(true);
    }
    
    public function setRuntimeEnd($time = null)
    {
        $this->_endTime = $time ?: microtime(true);
    }
    
    public function getRuntimeStart()
    {
        return $this->_startTime;
    }
    
    public function getRuntimeEnd()
    {
        return $this->_endTime;
    }
    
    public function getRuntime()
    {
        return sprintf("%.6F", $this->_endTime - $this->_startTime);
    }
    
    public function hasFailures()
    {
        return (bool)$this->_failures->count();
    }
    
    public function getFailures()
    {
        return $this->_failures;
    }
    
    public function getFailure(Failure $failure)
    {
        return $this->_failures[$failure];
    }
    
    public function hasErrors()
    {
        return (bool)$this->_errors->count();
    }
    
    public function getErrors()
    {
        return $this->_errors;
    }
    
    public function getError(Error $error)
    {
        return $this->_errors[$error];
    }
    
    public function hasExceptions()
    {
        return (bool)$this->_exceptions->count();
    }
    
    public function getExceptions()
    {
        return $this->_exceptions;
    }
    
    public function getException(Exception $exception)
    {
        return $this->_exceptions[$exception];
    }
    
    public function hasPendingExamples()
    {
        return (bool)$this->_pendingExamples->count();
    }
    
    public function getPendingExamples()
    {
        return $this->_pendingExamples;
    }
    
    public function getPending(Pending $pending)
    {
        return $this->_pendingExamples[$pending];
    }
    
    public function getPassing()
    {
        return $this->_passing;
    }
}