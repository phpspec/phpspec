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
class PHPSpec_Specification_Scalar extends PHPSpec_Specification
{

    protected $_scalarValue = null;

    public function __construct($scalarValue = null)
    {
        if (!is_null($scalarValue)) {
            $this->_scalarValue = $scalarValue;
            $this->setActualValue($this->_scalarValue);
        }
        $this->_expectation = new PHPSpec_Expectation;
    }

    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        throw new PHPSpec_Exception('unknown method called');
    }

    public function __get($name)
    {
        $dslResult = parent::__get($name);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        throw new PHPSpec_Exception('unknown property requested');
    }

    public function getScalar()
    {
        if (is_null($this->_scalarValue)) {
            throw new PHPSpec_Exception('a scalar value has not yet been initialised');
        }
        return $this->_scalarValue;
    }

}