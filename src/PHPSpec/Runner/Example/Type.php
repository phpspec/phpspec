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
 * @see \PHPSpec\Runner\Example
 */
use \PHPSpec\Runner\Example;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Type
{

    /**
     * The current example
     * 
     * @var \PHPSpec\Runner\Example
     */
    protected $_example = null;
    
    /**
     * Whether the example has passed
     * 
     * @var boolean
     */
    protected $_isPass = false;
    
    /**
     * Whether the example has failed
     * 
     * @var boolean
     */
    protected $_isFail = false;
    
    /**
     * Whether an exception was thrown
     * 
     * @var boolean
     */
    protected $_isException = false;
    
    /**
     * Whether an error was triggered
     * 
     * @var boolean
     */
    protected $_isError = false;
    
    /**
     * Whether the example was marked as pending
     * 
     * @var boolean
     */
    protected $_isPending = false;
    
    /**
     * Whether the example was marked to fail deliberately
     * 
     * @var boolean
     */
    protected $_isDeliberateFail = false;

    /**
     * Type is constructed with the example
     * 
     * @param \PHPSpec\Runner\Example $example
     */
    public function __construct(Example $example)
    {
        $this->_example = $example;
    }

    /**
     * Gets the example
     * 
     * @return \PHPSpec\Runner\Example
     */
    public function getExample()
    {
        return $this->_example;
    }

    /**
     * Checks whether example has passed
     * 
     * @return boolean
     */
    public function isPass()
    {
        return $this->_isPass;
    }

    /**
     * Checks whehter the example has failed
     * 
     * @return boolean
     */
    public function isFail()
    {
        return $this->_isFail;
    }

    /**
     * Checks whether an exception was thrown for this example
     * 
     * @return boolean
     */
    public function isException()
    {
        return $this->_isException;
    }

    /**
     * Checks whether an error has been triggered for this example
     * 
     * @return boolean
     */
    public function isError()
    {
        return $this->_isError;
    }
    
    /**
     * Checks whether this example has been marked as pending
     * 
     * @return boolean
     */
    public function isPending()
    {
        return $this->_isPending;
    }

    /**
     * Checks whether this example has been marked as failed
     * 
     * @return boolean
     */
    public function isDeliberateFail()
    {
        return $this->_isDeliberateFail;
    }

    /**
     * Proxies calls to example
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
    }

}