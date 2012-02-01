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
namespace PHPSpec\Matcher;

use PHPSpec\Matcher\InvalidMatcher;

 /**
  * @category   PHPSpec
  * @package    PHPSpec
  * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
  * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
  *                                     Marcello Duarte
  * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
  */
class MatcherFactory
{
    /**
     * Paths to matchers
     *
     * @var array
     */
    protected $_pathsToMatchers;
    
    /**
     * List of builtin matchers
     * 
     * @var array
     */
    protected $_matchers = array(
        'be', 'beAnInstanceOf', 'beEmpty', 'beEqualTo', 'beFalse',
        'beGreaterThan', 'beGreaterThanOrEqualTo', 'beInteger',
        'beLessThan', 'beLessThanOrEqualTo', 'beNull', 'beString', 'beTrue',
        'equal', 'match', 'throwException'
    );
    
    /**
     * Matcher factory is created with a path to matchers
     *
     * @param array $pathsToMatchers 
     */
    public function __construct(array $pathsToMatchers = array())
    {
        $this->_pathsToMatchers = $pathsToMatchers;
        $this->_namespace = '\PHPSpec\Matcher\\';
    }
    
    /**
     * Create the matcher
     *
     * @param string $matcherName 
     * @param string $expected 
     * @return \PHPSpec\Matcher
     */
    public function create($matcherName, $expected = array())
    {
        if(!is_array($expected)) {
            $expected = array($expected);
        }
        
        if (!in_array($matcherName, $this->_matchers)) {
            throw new InvalidMatcher(
                "Call to undefined method $matcherName"
            );
        }
        $matcherClass = $this->_namespace . $matcherName;
        $reflectedMatcher = new \ReflectionClass($matcherClass);
        $matcher = $reflectedMatcher->newInstanceArgs($expected);

        return $matcher;
    }
    
}
