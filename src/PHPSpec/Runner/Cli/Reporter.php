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
namespace PHPSpec\Runner\Cli;

use \PHPSpec\Runner\Formatter,
    \PHPSpec\Runner\ReporterEvent,
    \PHPSpec\Runner\Reporter as BaseReporter;

use \PHPSpec\Specification\Result\Failure,
    \PHPSpec\Specification\Result\Error,
    \PHPSpec\Specification\Result\Exception,
    \PHPSpec\Specification\Result\Pending,
    \PHPSpec\Specification\Result\DeliberateFailure,
    \PHPSpec\Specification\Example;

use PHPSpec\Util\Backtrace;

use \PHPSpec\DeprecatedNotice;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Reporter extends BaseReporter
{
    /**
     * Message to be printed 
     * 
     * @var string
     */
    protected $_message = '';
    
    /**
     * Adds a failure to the formatters
     * 
     * @param \PHPSpec\Specification\Example        $example
     * @param \PHPSpec\Specification\Result\Failure $failure
     */
    public function addFailure(Example $example, Failure $failure)
    {
        $this->_failures->attach($failure, $example);
        $this->notify(
            new ReporterEvent(
                'status', 'F', $example->getSpecificationText(),
                $failure->getMessage(),
                Backtrace::pretty($failure->getTrace()), $failure
            )
        );
    }
    
    /**
     * Adds a pass to the formatters
     * 
     * @param \PHPSpec\Specification\Example $example
     */
    public function addPass(Example $example)
    {
        $this->_passing[] = $example;
        $this->notify(
            new ReporterEvent(
                'status', '.', $example->getSpecificationText()
            )
        );
    }
    
    /**
     * Adds a deliberate failure to the formatters
     * 
     * @param \PHPSpec\Specification\Example                  $example
     * @param \PHPSpec\Specification\Result\DeliberateFailure $failure
     */
    public function addDeliberateFailure(Example $example,
                                         DeliberateFailure $failure)
    {
        $this->_failures->attach($failure, $example);
        $this->notify(
            new ReporterEvent(
                'status', 'F', $example->getSpecificationText(),
                $failure->getMessage(),
                Backtrace::pretty($failure->getTrace()), $failure
            )
        );
    }
    
    /**
     * Adds an error to the formatters
     * 
     * @param \PHPSpec\Specification\Example      $example
     * @param \PHPSpec\Specification\Result\Error $error
     */
    public function addError(Example $example, Error $error)
    {
        $this->getErrors()->attach($error, $example);
        $this->notify(
            new ReporterEvent(
                'status', 'E', $example->getSpecificationText(),
                $error->getMessage(),
                Backtrace::pretty($error->getTrace()), $error
            )
        );
    }
    
    /**
     * Adds an exception to the formatters
     * 
     * @param \PHPSpec\Specification\Example      $example
     * @param \Exception                          $e
     */
    public function addException(Example $example, \Exception $e)
    {
        $this->getExceptions()->attach($e, $example);
        $this->notify(
            new ReporterEvent(
                'status', 'E', $example->getSpecificationText(),
                $e->getMessage(), Backtrace::pretty($e->getTrace()), $e
            )
        );
    }
    
    /**
     * Adds a pending to the formatters
     * 
     * @param \PHPSpec\Specification\Example        $example
     * @param \PHPSpec\Specification\Result\Pending $pending
     */
    public function addPending(Example $example, Pending $pending)
    {
        $this->_pendingExamples->attach($pending, $example);
        $this->notify(
            new ReporterEvent(
                'status', '*', $example->getSpecificationText(),
                $pending->getMessage()
            )
        );
    }
    
    /**
     * Sets the message
     * 
     * @param string $string
     * @param boolean $newLine
     */
    public function setMessage($string, $newLine = true)
    {
        $this->_message .= $string . ($newLine ? PHP_EOL : '');
    }
    
    /**
     * Gets the message
     * 
     * @return string
     */
    public function getMessage()
    {
        return $this->_message;
    }
    
    /**
     * Whether there is a message set
     * 
     * @param boolean
     */
    public function hasMessage()
    {
        return (bool)strlen($this->_message);
    }
    
    /**
     * Adds a formatter
     * 
     * @param \PHPSpec\Runner\Formatter $formatter
     */
    public function addFormatter(Formatter $formatter)
    {
        $this->_formatters[] = $formatter;
    }
    
    /**
     * Set the formatter
     * 
     * @deprecated
     * @param \PHPSpec\Runner\Formatter $formatter
     */
    public function setFormatter(Formatter $formatter)
    {
        $this->_formatters[] = $formatter;
        throw new DeprecatedNotice(
            "setFormatter is deprecate, please use addFormatter"
        );
    }
    
    /**
     * Get the formatters
     * 
     * @return SplObjectStorage
     */
    public function getFormatters()
    {
        return $this->_formatters;
    }
}