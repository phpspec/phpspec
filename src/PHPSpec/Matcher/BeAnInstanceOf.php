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
class PHPSpec_Matcher_BeAnInstanceOf implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = $expected;
    }

    public function matches($actual)
    {
        if ($actual instanceof $this->_expected) {
            $this->_actual = $this->_expected;
            return true;
        } else {
            if (is_object($actual)) {
                $this->_actual = get_class($actual);
            } elseif (is_null($actual)) {
                $this->_actual = 'NULL';
            } else {
                $this->_actual = $actual;
            }
        }
        return false;
    }

    public function getFailureMessage()
    {
        return 'expected ' . var_export($this->_expected, true) . ', got ' . var_export($this->_actual, true) . ' (using beAnInstanceOf())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . var_export($this->_actual, true) . ' not to be ' . var_export($this->_expected, true) . ' (using beAnInstanceOf())';
    }

    public function getDescription()
    {
        return 'be an instance of ' . var_export($this->_expected, true);
    }
}