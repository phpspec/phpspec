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
class PHPSpec_Matcher_Predicate extends PHPSpec_Matcher_BeTrue
{
   
    protected $_object = null;

    protected $_method = null;

    protected $_predicateCall = null;
    
    public function __construct($expected)
    {
        parent::__construct($expected);
    }

    public function setObject($object)
    {
        if (!is_object($object)) {
            throw new PHPSpec_Exception('not an object');
        }
        $this->_object = $object;
    }

    public function setMethodName($method)
    {
        $this->_method = $method;
    }

    public function setPredicateCall($callName)
    {
        $this->_predicateCall = $callName;
    }

    public function matches($UnusedParamSoIgnore)
    {
        $this->_actual = $this->_object->{$this->_method}();
        if (!is_bool($this->_actual)) {
            return false;
        }
        return $this->_expected == $this->_actual;
    }

    public function getFailureMessage()
    {
        return 'expected TRUE, got FALSE or non-boolean (using ' . $this->_predicateCall . '())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected FALSE or non-boolean not TRUE (using ' . $this->_predicateCall . '())';
    }

    public function getDescription()
    {
        $call = $this->_predicateCall;
        $terms = preg_split("/(?=[[:upper:]])/", $call, -1, PREG_SPLIT_NO_EMPTY);
        $termsLowercase = array_map('strtolower', $terms);
        return implode(' ', $termsLowercase);
    }
}