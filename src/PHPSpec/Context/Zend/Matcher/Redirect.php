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

class Redirect implements Matcher
{
    /**
     * Matcher is usually constructed with the expected value
     * but redirect() is itself the expectation
     * 
     * @param unused $expected
     */
    public function __construct($notUsed)
    {
    }

    /**
     * Checks whether actual value is true
     * 
     * @param Response $response
     * @return boolean
     */
    public function matches($response)
    {
        $constraint = new \Zend_Test_PHPUnit_Constraint_Redirect();
        if (!$constraint->evaluate($response, 'assertRedirect')) {
            return false;
        };
        return true;
    }

    /**
     * Returns failure message in case we are using should
     * 
     * @return string
     */
    public function getFailureMessage()
    {
        return 'expected to redirect, got no redirection (using redirect())';
    }

    /**
     * Returns failure message in case we are using should not
     * 
     * @return string
     */
    public function getNegativeFailureMessage()
    {
        return 'expected not to redirect, but redirected (using redirect())';
    }

    /**
     * Returns the matcher description
     * 
     * @return string
     */
    public function getDescription()
    {
        return 'redirect';
    }
}