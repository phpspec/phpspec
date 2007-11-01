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
class PHPSpec_Matcher_Be extends PHPSpec_Matcher_Equal implements PHPSpec_Matcher_Interface
{

    public function getFailureMessage()
    {
        return 'expected ' . strval($this->_expected) . ', got ' . strval($this->_actual) . ' (using be())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected ' . strval($this->_actual) . ' not to be ' . strval($this->_expected) . ' (using be())';
    }

    public function getDescription()
    {
        return 'be ' . strval($this->_expected);
    }
}