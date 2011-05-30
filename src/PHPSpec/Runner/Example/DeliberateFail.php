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
namespace PHPSpec\Runner\Example;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class DeliberateFail extends Type
{

    /**
     * Whether this is an exception
     * 
     * @var bool
     */
    protected $_isException = false;
    
    /**
     * Not quite
     * 
     * @var bool
     */
    protected $_isFail = false;
    
    /**
     * Whether this exception result on deliberate fail() call
     * 
     * @var bool
     */
    protected $_isDeliberateFail = true;
    
    /**
     * @var string
     */
    protected $_line = '';
    
    /**
     * @var Exception
     */
    protected $_exception;
    
    /**
     * The Exception is constructed with the example and the example thrown in
     * it
     * 
     * @param \PHPSpec\Runner\Example $example
     * @param \Exception $e
     */
    public function __construct(\PHPSpec\Runner\Example $example, \Exception $e)
    {
        parent::__construct($example);
        $this->_exception = $e;
    }
    
    /**
     * Sets the line
     * 
     * @param string $line
     */
    public function setLine($line)
    {
        $this->_line = $line;
    }
    
    /**
     * Gets the line
     * 
     * @return string
     */
    public function getLine()
    {
        return $this->_line;
    }
    
    public function getMessage()
    {
        return $this->_exception->getMessage();
    }

}