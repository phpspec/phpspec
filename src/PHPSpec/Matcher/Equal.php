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
class PHPSpec_Matcher_Equal
{

    protected $_expected = null;

    protected $_actual = null;

    protected $_epsilon = null;

    public function __construct($expected)
    {
        $this->_expected = $expected;
    }

    public function matches($actual, $epsilon = null)
    {
        if (!is_null($epsilon)) {
            $this->_epsilon = $epsilon;
        }
        $this->_actual = $actual;
        $type = gettype($actual);

        // are they both arrays or objects?
        if (is_array($this->_expected) XOR is_array($this->_actual)) {
            return false;
        }
        if (is_object($this->_expected) XOR is_object($this->_actual)) {
            return false;
        }
        if (is_object($this->_expected) && is_object($this->_actual) && (get_class($this->_expected) !== get_class($this->_actual))) {
            return false;
        }

        if (is_array($this->_actual) && is_array($this->_expected)) {
            // compare arrays - we'll curently enforce key equality
            return $this->_expected == $this->_actual;
        }

        if (!is_array($this->_expected) && !is_array($this->_actual) && !is_object($this->_expected) && !is_object($this->_actual)) {
            // scalar comparisons
            switch ($type) {
                case 'integer':
                case 'float':
                    if (is_null($this->_epsilon)) {
                        return ($this->_expected == $this->_actual);
                    } else {
                        // float comparison using expected epsilon
                        return (abs($this->_expected - $this->_actual) <= $this->_epsilon);
                    }
                    break;
                default:
                    return $this->_expected == $this->_actual;
                    break;
            }
        }

        return true;
    }

    public function getFailureMessage()
    {
        return 'expected ' . strval($this->_expected) . ', got ' . strval($this->_actual) . ' (using equal())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . strval($this->_actual) . ' not to equal ' . strval($this->_expected) . ' (using equal())';
    }

    public function getDescription()
    {
        return 'equal ' . strval($this->_expected);
    }
}