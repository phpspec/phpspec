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
class PHPSpec_Context_Zend_Matcher_HaveText extends PHPSpec_Matcher_Match
{

    public function __construct($expected)
    {
        // this is of course total bullshit for HTML but first things first
        $this->_expected = "/" . str_replace('/', '\/', $expected) . "/";
    }

    public function getFailureMessage()
    {
        return 'expected match for /' . strval($this->_expected) . '/, but got no matching text (using haveText())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected no match for /' . strval($this->_expected) . '/, but got matching text (using haveText())';
    }

    public function getDescription()
    {
        return 'have text of ' . strval($this->_expected);
    }

}