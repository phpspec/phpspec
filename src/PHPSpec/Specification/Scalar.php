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
 * to license@phpspec.net so we can send you a copy immediately.
 *
 * @category  PHPSpec
 * @package   PHPSpec
 * @copyright Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Specification;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Scalar extends \PHPSpec\Specification
{

    protected $_scalarValue = null;

    /**
     * @param $scalarValue
     */
    public function __construct($scalarValue = null)
    {
        if (!is_null($scalarValue)) {
            $this->_scalarValue = $scalarValue;
            $this->setActualValue($this->_scalarValue);
        }
        $this->_expectation = new \PHPSpec\Expectation;
    }

    /**
     * @throws \PHPSpec\Exception
     * @param mixed $method
     * @param mixed $args
     * @return mixed An instance of self or TRUE if a Matcher was run
     */
    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        throw new \PHPSpec\Exception('unknown method called');
    }

    /**
     * @throws \PHPSpec\Exception
     * @param string $name
     * @return \PHPSpec\Specification\Scalar An instance of self
    */
    public function __get($name)
    {
        $dslResult = parent::__get($name);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        throw new \PHPSpec\Exception('unknown property requested');
    }

    /**
     * @throws \PHPSpec\Exception
     * @return mixed
    */
    public function getScalar()
    {
        if (is_null($this->_scalarValue)) {
            throw new \PHPSpec\Exception(
                'a scalar value has not yet been initialised'
            );
        }
        return $this->_scalarValue;
    }

}