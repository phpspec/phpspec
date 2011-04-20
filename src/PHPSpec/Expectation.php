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
namespace PHPSpec;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Expectation
{

    /**
     * A boolean value indicating whether the attached Matcher is
     * expected to pass (TRUE) or fail (FALSE). This is the fundamental basis
     * of setting expectation based on the terms "should" and "should not".
     *
     * @var bool
     */
    protected $_expectedMatcherResult = true;

    /**
     * Sets a positive expectation that the attached Matcher will pass
     *
     * @return null
     */
    public function should()
    {
        $this->_expectedMatcherResult = true;
        return $this;
    }

    /**
     * Sets a negative expectation that the attached Matcher will fail
     *
     * @return null
     */
    public function shouldNot()
    {
        $this->_expectedMatcherResult = false;
        return $this;
    }

    /**
     * Gets the expectation in a boolean form
     *
     * @return bool
     */
    public function getExpectedMatcherResult()
    {
        return $this->_expectedMatcherResult;
    }

    /**
     * Returns the string interpretation of the current expectation.
     * "should" for TRUE and "should not" for FALSE.
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->getExpectedMatcherResult() === true) {
            return  'should';
        }
        return 'should not';
    }

}