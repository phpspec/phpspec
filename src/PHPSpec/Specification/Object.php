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
class PHPSpec_Specification_Object extends PHPSpec_Specification
{

    /**
     * Interrogator object utilised to mediate between the DSL and an Object
     * value being specified.
     *
     * @var PHPSpec_Object_Interrogator
     */
    protected $_interrogator = null;

    public function __construct(PHPSpec_Object_Interrogator $interrogator)
    {
        $this->_interrogator = $interrogator;
        $this->_expectation = new PHPSpec_Expectation;

        // default actual will be the object itself
        $this->setActualValue($interrogator->getSourceObject());
    }

    public function __call($method, $args)
    {
        $dslResult = parent::__call($method, $args);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        $this->setActualValue(call_user_func_array(array($this->_interrogator, $method), $args));
        return $this;
    }

    public function __get($name)
    {
        $dslResult = parent::__get($name);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        $this->setActualValue($this->_interrogator->{$name});
        return $this;
    }

    public function getInterrogator()
    {
        if (is_null($this->_interrogator)) {
            throw new PHPSpec_Exception('an Interrogator has not yet been created');
        }
        return $this->_interrogator;
    }

    protected function __set($name, $value)
    {
        $this->_interrogator->{$name} = $value;
    }

    protected function __isset($name)
    {
        return isset($this->_interrogator->{$name});
    }

    protected function __unset($name)
    {
        unset($this->_interrogator->{$name});
    }

}