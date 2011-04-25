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
namespace PHPSpec\Runner;

/**
 * @see \PHPSpec\Runner\Result
 */
use PHPSpec\Runner\Result;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
abstract class Reporter
{

    /**
     * The result
     * 
     * @var \PHPSpec\Runner\Result
     */
    protected $_result; 
    
    /**
     * Whether to show colors in the result
     * 
     * @var boolean
     */
    protected $_showColors = false; 
    
    /**
     * Whether to display specdox format
     * 
     * @var boolean
     */
    protected $_doSpecdox = false;

    /**
     * Reporter is constructed with the result
     * 
     * @param Result $result
     */
    public function __construct(Result $result)
    {
        $this->_result = $result;
    }

    /**
     * Sets the reporter to display specdox
     * 
     * @param boolean $bool
     */
    public function doSpecdox($bool = true)
    {
        $this->_doSpecdox = $bool;
    }

    /**
     * Sets the reporter to show colors in the result
     * 
     * @param boolean $show
     */
    public function showColors($show)
    {
        $this->_showColors = $show;
    }

    /**
     * Returns the result of all specs
     * 
     * @return string
     */
    abstract public function toString();

    /**
     * Gets the descriptions of all examples
     * 
     * @return string
     */
    abstract public function getSpecdox();

    /**
     * Returns the result of all specs, allows the object to be printed
     * 
     * @return string
     */
    abstract public function __toString();

}