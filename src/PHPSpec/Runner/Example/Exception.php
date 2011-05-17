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
namespace PHPSpec\Runner\Example;

/**
 * @see \PHPSpec\Util\Backtrace
 */
use PHPSpec\Util\Backtrace;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Exception extends Type
{

    /**
     * @var boolean
     */
    protected $_isException = true;

    /**
     * @var \Exception
     */
    protected $_exception = null;

    /**
     * The Exception is constructed with the example and the example thrown in
     * it
     * 
     * @param \PHPSpec\Runner\Example $example
     * @param \Exception $e
     */
    public function __construct(\PHPSpec\Runner\Example $example, \Exception $e)
    {
        parent::__construct($example);
        $this->_exception = $e;
    }

    /**
     * Gets the exception
     * 
     * @return \Exception
     */
    public function getException()
    {
        return $this->_exception;
    }

    /**
     * Converts the exception to string (returns its message)
     * 
     * @return string
     */
    public function toString()
    {
        return 'Failure/Exception: ' . $this->getSourceFromLine() . PHP_EOL .
               '     ' . get_class($this->_exception) . ': ' . 
               (string) $this->_exception->getMessage();
    }
    
    public function getSourceFromLine()
    {
        $trace = $this->_exception->getTrace();
        return Backtrace::code($trace);
    }

    /**
     * Returns a slice of the exception trace formatted nicely. The size of the
     * slice is determined by the argument <code>$lines</code> 
     * 
     * @param integer $lines
     * @return string
     */
    public function getPrettyTrace($lines)
    {
        return Backtrace::pretty($this->_exception->getTrace(), $lines);
    }

    /**
     * Proxies the call to the exception to its example and then to its
     * exception property
     * 
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (method_exists($this->_example, $method)) {
            return call_user_func_array(array($this->_example, $method), $args);
        }
        if (method_exists($this->_exception, $method)) {
            return call_user_func_array(
                array($this->_exception, $method), $args
            );
        }
    }

}