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
 */
class PHPSpec_Runner_Example_Exception extends PHPSpec_Runner_Example_Type
{

    protected $_isException = true;

    protected $_exception = null;

    public function __construct(PHPSpec_Runner_Example $example, Exception $e)
    {
        parent::__construct($example);
        $this->_exception = $e;
    }

    public function getException()
    {
        return $this->_exception;
    }

    public function toString()
    {
        return (string) $this->_exception;
    }

    public function __call($method, $args)
    {
        if (method_exists($this->_example, $method)) {
            return call_user_func_array(array($this->_example, $method), $args);
        }
        if (method_exists($this->_exception, $method)) {
            return call_user_func_array(array($this->_exception, $method), $args);
        }
    }

}