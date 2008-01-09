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
class PHPSpec_Context implements Countable
{

    /**
     * Description of the current Context under which we are writing
     * specifications.
     *
     * @var string
     */
    protected $_description = null;

    /**
     * An array of all method names which follow the naming convention
     * for executable examples (specs)
     *
     * @var array
     */
    protected $_specMethods = array();

    /**
     * The number of executable examples found in this Context
     *
     * @var int
     */
    protected $_count = 0;

    /**
     * The Domain Specific Language object utilised to specify
     * expectations for behaviour
     *
     * @var PHPSpec_Specification
     */
    protected $_specificationDsl = null;

    /**
     * Constructor; Create a new Context for behaviour examples with any relevant
     * details built at run time concerning specification strings, context
     * descriptions, and executable examples to run.
     */
    public function __construct()
    {
        $this->_buildDetails();
    }

    /**
     * Generate a Specification (DSL) object based on the passed value whether
     * an object or scalar value.
     *
     * @param mixed $value
     * @return PHPSpec_Specification
     */
    public function spec($value)
    {
        $this->_specificationDsl = PHPSpec_Specification::getSpec($value);
        return $this->_specificationDsl;
    }

    /**
     * Return the last Specification (DSL) object utilised for this Context
     *
     * @return PHPSpec_Specification
     */
    public function getCurrentSpecification()
    {
        return $this->_specificationDsl;
    }

    /**
     * Set a textual description (specdox style) for this Context
     *
     * @param string $description
     * @return null
     */
    public function setDescription($description)
    {
        $this->_description = $description;
    }

    /**
     * Return the textual description (specdox style) for this Context
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->_description;
    }

    /**
     * Get an array of the methods available in this Context which qualify as
     * executable examples for expected behaviour.
     *
     * @return array
     */
    public function getSpecMethods()
    {
        return $this->_specMethods;
    }

    /**
     * Returns the number of qualifying executable methods found
     *
     * @return int
     */
    public function count()
    {
        return $this->getSpecificationCount();
    }

    /**
     * Returns the number of qualifying executable methods found
     *
     * @return int
     */
    public function getSpecificationCount()
    {
        return $this->_count;
    }

    /**
     * Return the Context file path
     *
     * @return string The filepath to this Context class
     */
    public function getFileName()
    {
        $reflected = new ReflectionObject($this);
        return $reflected->getFileName();
    }
    
    /**
     * Set status of the current example from which called to Pending, i.e.
     * awaiting completion.
     * 
     * @return null
     */
    public function pending($message = null)
    {
        if (is_null($message)) {
        	$message = 'Incomplete';
        }
        
    	throw new PHPSpec_Runner_PendingException($message);
    }

    public function fail($message = null)
    {
        if (is_null($message)) {
        	$message = 'Deliberate Fail';
        }

        throw new PHPSpec_Runner_DeliberateFailException($message);
    }

    public function clearCurrentSpecification()
    {
        $this->_specificationDsl = null;
    }
    
    /**
     * Based on the Context object generate all necessary data required
     * in order to count, retrieve and execute the specs/examples held
     * in this context
     *
     * @return null
     */
    protected function _buildDetails()
    {
        $object = new ReflectionObject($this);
        $class = $object->getName();
        if (!preg_match("/.*(spec)$/i", $class) && !preg_match("/^(describe).*/i", $class)) {
            throw new Exception('behaviour context did not end with \'Spec\' or \'spec\', or did not start with \'Describe\' or \'describe\'');
        }
        $this->_addSpecifications($object->getMethods());
        $this->_addDescription($class);
    }

    /**
     * Generate and add a description for this Context. The description
     * is basically the Class name split and concatenated with spaces.
     *
     * @return null
     */
    protected function _addDescription($class)
    {
        if (!is_string($class)) {
            return false;
        }
        $terms = preg_split("/(?=[[:upper:]])/", $class, -1, PREG_SPLIT_NO_EMPTY);
        $termsLowercase = array_map('strtolower', $terms);
        $this->setDescription(implode(' ', $termsLowercase));
    }

    /**
     * Locate and add qualifying method names which are intended as specs or
     * executable examples.
     *
     * @return null
     */
    protected function _addSpecifications($methods)
    {
        foreach ($methods as $method) {
            $name = $method->getName();
            if (substr($name, 0, 2) == 'it') {
                $this->_addSpecMethod($name);
                $this->_setSpecificationCount( $this->getSpecificationCount() + 1 );
            }
        }
    }

    /**
     * Add a spec/example method to the list of qualifying methods
     *
     * @return null
     */
    protected function _addSpecMethod($method)
    {
        $this->_specMethods[] = $method;
    }

    /**
     * Set the internal count of specs/examples
     *
     * @return null
     */
    protected function _setSpecificationCount($i)
    {
        $this->_count = $i;
    }

}