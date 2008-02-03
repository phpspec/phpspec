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
class PHPSpec_Context_Zend_Matcher_BeSuccess implements PHPSpec_Matcher_Interface
{

    protected $_expected = null;

    protected $_actual = null;

    public function __construct($expected)
    {
        $this->_expected = '200';
    }

    public function matches($actual)
    {
        $this->_actual = $actual;
        if (!$actual instanceof PHPSpec_Context_Zend_Response) {
            return false;
        }
        return $this->_expected == $this->_actual->getHttpResponseCode();
    }

    public function getFailureMessage()
    {
        return 'expected success (200), got response code (' . $this->_actual->getHttpResponseCode() . ') or invalid response object (using beSuccess())';
    }

    public function getNegativeFailureMessage()
    {
        return 'expected response code not to be (200), but got success (200) (using beSuccess())';
    }

    public function getDescription()
    {
        return 'be success';
    }
}