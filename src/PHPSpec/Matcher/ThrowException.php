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
namespace PHPSpec\Matcher;

/**
 * @see \PHPSpec\Matcher
 */
use PHPSpec\Matcher;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class ThrowException implements Matcher
{
    /**
     * The exception class name you are comparing with
     * 
     * @var mixed
     */
    protected $_expectedException;
    
    /**
     * The exception message are comparing with
     * 
     * @var mixed
     */
    protected $_expectedMessage;

    /**
     * The actual exception class name
     * 
     * @var object
     */
    protected $_actualException;

    /**
     * The actual message
     * 
     * @var object
     */
    protected $_actualMessage;
    
    /**
     * Constructs the matcher with the value expected
     * 
     * @param mixed $exception
     */
    public function __construct($exception, $message = null)
    {
        $this->_expectedException = $exception;
        $this->_expectedMessage = $message;
    }
    
    /**
     * Describes Matcher specific implementation 
     *
     * @param mixed $actualException
     */
    public function matches($actualException, $actualMessage = null)
    {
        $this->_actualException = $actualException;
        $this->_actualMessage = $actualMessage;
        return $this->_actualException === $this->_expectedException &&
               $this->_actualMessage === $this->_expectedMessage;
    }

    /**
     * Returns the failure message to be displayed
     */
    public function getFailureMessage()
    {
        if (isset($this->_expectedException)) {
            if (isset($this->_expectedMessage)) {
                if ($this->_expectedMessage !== $this->_actualMessage) {
                    return 'expected to throw exception with message ' .
                       var_export($this->_expectedMessage, true) .
                       ', got ' . var_export($this->_actualMessage, true) .
                       ' (using throwException())';
                }
            }
            
            if ($this->_expectedException !== $this->_actualException) {
                return 'expected to throw exception ' .
                       var_export($this->_expectedException, true) .
                       ', got ' . var_export($this->_actualException, true) .
                       ' (using throwException())';
            }
        }        
    }

    /**
     * Returns the negative failure message in case
     * of using should not instead of should
     */
    public function getNegativeFailureMessage()
    {
        if (isset($this->_expectedException)) {
            if (isset($this->_expectedMessage)) {
                if ($this->_expectedMessage !== $this->_actualMessage) {
                    return 'expected ' .
                           var_export($this->_actualException, true) .
                           ' not for exception message but got ' .
                           var_export($this->_expectedException, true) .
                           ' (using throwException())';
                }
            }
        }
        
        if ($this->_expectedException !== $this->_actualException) {
            return 'expected ' . var_export($this->_actualException, true) .
                   ' not to be thrown but got ' .
                   var_export($this->_expectedException, true) .
                   ' (using throwException())';
        }
    }

    /**
     * Describes the matching
     */
    public function getDescription()
    {
        return 'throw exception ' . strval($this->_expectedException) .
               (isset($this->_expectedMessage) ? ' with message ' .
               $this->_expectedMessage : '');
    }
}