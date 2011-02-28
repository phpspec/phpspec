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
class PHPSpec_Matcher_BeInteger implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = null;
    }

    public function matches($actual)
    {
        $this->_actual = $actual;
        return is_int($this->_actual);
    }

    public function getFailureMessage()
    {
		$type = is_object($this->_actual) ? get_class($this->_actual) : gettype($this->_actual);
        return 'expected to be integer, got ' . var_export($this->_actual, true) . ' type of ' . $type . ' (using beInteger())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . var_export($this->_actual, true) . ' not to be integer (using beInteger())';
    }

    public function getDescription()
    {
        return 'be integer';
    }
}