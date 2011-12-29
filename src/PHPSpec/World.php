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
 * @copyright Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec;

use \PHPSpec\Runner\Reporter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class World
{
    
    /**
     * Parsed options
     *
     * @var array
     */
    protected $_options;
    
    /**
     * The reporter
     *
     * @var \PHPSpec\Runner\Reporter
     */
    protected $_reporter;
    
    /**
     * Gets a option
     * 
     * @param string $name
     * @return mixed
     */
    public function getOption($name)
    {
        return isset($this->_options[$name]) ? $this->_options[$name] : null;
    }
    
    /**
     * Sets the option
     * 
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function setOption($name, $value)
    {
        if (isset($this->_options[$name])) {
            return $this->_options[$name] = $value;            
        }
        return false;
    }
    
    /**
     * Gets an option
     * 
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }
    
    /**
     * Sets the options
     * 
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->_options = $options;
    }
    
    /**
     * Gets the reporter
     * 
     * @return \PHPSpec\Runner\Reporter
     */
    public function getReporter()
    {
        return $this->_reporter;
    }
    
    /**
     * Sets the reporter
     * 
     * @param \PHPSpec\Runner\Reporter $reporter
     */
    public function setReporter($reporter)
    {
        $this->_reporter = $reporter;
    }
}