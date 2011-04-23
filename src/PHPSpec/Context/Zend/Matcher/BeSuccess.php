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
namespace PHPSpec\Context\Zend\Matcher;

/**
 * @see \PHPSpec\Matcher
 */
use \PHPSpec\Matcher;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class BeSuccess implements Matcher
{

    /**
     * The expected status code. 200 for beSuccess.
     * 
     * @var mixed
     */
    protected $_expected = null;

    /**
     * The actual status code
     * 
     * @var unknown_type
     */
    protected $_actual = null;

    /**
     * Matcher is usually constructed with the expected but here we just use
     * status code 200
     * 
     * @param unknown_type $expected
     */
    public function __construct($expected)
    {
        $this->_expected = '200';
    }

    
    /**
     * Tries to match the response status code to 200
     * @see PHPSpec.Matcher::matches()
     * 
     * @param mixed $expected
     * @return boolean
     */
    public function matches($actual)
    {
        $this->_actual = $actual;
        if (!$actual instanceof \PHPSpec\Context\Zend\Response) {
            return false;
        }
        return $this->_expected == $this->_actual->getHttpResponseCode();
    }

    /**
     * Returns failure message in case we are using should
     * 
     * @return string
     */
    public function getFailureMessage()
    {
        return 'expected success (200), got response code (' .
               $this->_actual->getHttpResponseCode() .
               ') or invalid response object (using beSuccess())';
    }

    /**
     * Returns failure message in case we are using should not
     * 
     * @return string
     */
    public function getNegativeFailureMessage()
    {
        return 'expected response code not to be (200), ' .
               'but got success (200) (using beSuccess())';
    }

    /**
     * Returns the matcher description
     * 
     * @return string
     */
    public function getDescription()
    {
        return 'be success';
    }
}