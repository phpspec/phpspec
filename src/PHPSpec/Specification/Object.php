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
 * @see \PHPSpec\Specification.php
 */
use \PHPSpec\Specification;

/**
 * @see \PHPSpec\Object\Interrogator.php
 */
use \PHPSpec\Object\Interrogator;
/**
 * @see \PHPSpec\Expectation.php
 */
use \PHPSpec\Expectation;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Object extends Specification
{

    /**
     * Interrogator object utilised to mediate between the DSL and an Object
     * value being specified.
     *
     * @var \PHPSpec\Object\Interrogator
    */
    protected $_interrogator = null;

    /**
     * Constructor; Specification is created using an interrogator
     * 
     * * @param Interrogator $interrogator
    */
    public function __construct(Interrogator $interrogator)
    {
        $this->_interrogator = $interrogator;
        $this->_expectation = new \PHPSpec\Expectation;

        // default actual will be the object itself
        $this->setActualValue($interrogator->getSourceObject());
    }

    /**
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

        $this->setActualValue(
            call_user_func_array(
                array($this->_interrogator, $method), $args
            )
        );
        return $this;
    }

    /**
     * @param string $name
     * @return \PHPSpec\Specification\Object An instance of self
    */
    public function __get($name)
    {
        $dslResult = parent::__get($name);
        if (!is_null($dslResult)) {
            return $dslResult;
        }

        $this->setActualValue($this->_interrogator->{$name});
        return $this;
    }

    /**
     * @throws \PHPSpec\Exception
     * @return Interrogator
    */
    public function getInterrogator()
    {
        if (is_null($this->_interrogator)) {
            throw new \PHPSpec\Exception(
                'an Interrogator has not yet been created'
            );
        }
        return $this->_interrogator;
    }

    /**
     * @param string $name
     * @param mixed $value
    */
    public function __set($name, $value)
    {
        $this->_interrogator->{$name} = $value;
    }

    /**
     * @param string $name
     * @return boolean
    */
    public function __isset($name)
    {
        return isset($this->_interrogator->{$name});
    }

    /**
     * @param string $name
    */
    public function __unset($name)
    {
        unset($this->_interrogator->{$name});
    }

}