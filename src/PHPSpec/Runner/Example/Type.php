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
class PHPSpec_Runner_Example_Type
{

    protected $_example = null;
    protected $_isPass = false;
    protected $_isFail = false;
    protected $_isException = false;
    protected $_isError = false;
    protected $_isPending = false;
    protected $_isDeliberateFail = false;

    public function __construct(PHPSpec_Runner_Example $example)
    {
        $this->_example = $example;
    }

    public function getExample()
    {
        return $this->_example;
    }

    public function isPass()
    {
        return $this->_isPass;
    }

    public function isFail()
    {
        return $this->_isFail;
    }

    public function isException()
    {
        return $this->_isException;
    }

    public function isError()
    {
        return $this->_isError;
    }
    
    public function isPending()
    {
        return $this->_isPending;
    }

    public function isDeliberateFail()
    {
        return $this->_isDeliberateFail;
    }

    public function __call($method, $args)
    {
        if (method_exists($this->_example, $method)) {
            return call_user_func_array(array($this->_example, $method), $args);
        }
    }

}