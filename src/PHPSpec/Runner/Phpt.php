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
 * @see \PHPSpec\Specification
 */
use \PHPSpec\Specification;

/**
 * @see \PHPSpec\Expectation
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
class Phpt
{
    /**
     * Notifies whether a spec's matcher result matches the expectation
     * 
     * @param Specification $spec
     * @param Expectation $expect
     */
    public function notify(Specification $spec, Expectation $expect)
    {
        if ($spec->getMatcherResult() === $expect->getExpectedMatcherResult()) {
            echo 'PASS';
            return;
        }
        if ($spec->getMatcherResult() === true) {
            echo $spec->getMatcherNegativeFailureMessage();
        }
        echo $spec->getMatcherFailureMessage();
    }
}