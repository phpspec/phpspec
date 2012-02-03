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

    const NAMESPACE_SEPARATOR = '\\';
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
    protected $_builtinMatchers = array(
        'be', 'beAnInstanceOf', 'beEmpty', 'beEqualTo', 'beFalse',
        'beGreaterThan', 'beGreaterThanOrEqualTo', 'beInteger',
        'beLessThan', 'beLessThanOrEqualTo', 'beNull', 'beString', 'beTrue',
        'equal', 'match', 'throwException'
    );

    /**
     * Matchers registry
     *
     * @var associative array
     */
    protected $_matchers = array();
    
    /**
     * Namespace for the builtin matchers
     *
     * @var string
     */
    protected $_buitinsNamespace;

    /**
     * Matcher factory is created with a path to matchers
     *
     * @param array $pathsToMatchers 
     */
    public function __construct(array $pathsToMatchers = array())
    {
        $this->_pathsToMatchers = $pathsToMatchers;
        $this->_buitinsNamespace = '\PHPSpec\Matcher\\';
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
        if (empty($this->_matchers)) {
            $this->_buildRegistry();
        }
        
        if (!is_array($expected)) {
            $expected = array($expected);
        }

        if (!in_array($matcherName, $this->_builtinMatchers)) {
            throw new InvalidMatcher(
                "Call to undefined method $matcherName"
            );
        }

        $matcherClass = $this->_buitinsNamespace . $matcherName;
        $reflectedMatcher = new \ReflectionClass($matcherClass);
        $matcher = $reflectedMatcher->newInstanceArgs($expected);

        return $matcher;
    }

    /**
     * Builds the matchers registry
     *
     * @return void
     */
    private function _buildRegistry()
    {
        $this->_addBuiltinMatchersToRegistry();
        $this->_addCustomMatchersToRegistry();
    }

    /**
     * Adds builtin matchers to the registry
     *
     * @return void
     */
    private function _addBuiltinMatchersToRegistry()
    {
        foreach ($this->_builtinMatchers as $buitinMatcher) {
            $this->_matchers[$buitinMatcher] = $this->_buitinsNamespace;
        }
    }

    /**
     * Adds custom matchers to the registry
     *
     * @return void
     */
    private function _addCustomMatchersToRegistry()
    {
        foreach ($this->_pathsToMatchers as $originalPath) {    
            $this->_recursivelyRegisterMatchersOnFolder($originalPath);
        }
    }

    /**
     * Recursively registers matchers found on folder
     *
     * @param string $folder 
     */
    private function _recursivelyRegisterMatchersOnFolder($originalPath)
    {
        $nameSpace = $this->_fromPathToNamespace($originalPath);
        $currentPath = $this->_findMatcherPath($originalPath);

        foreach (glob($currentPath . DIRECTORY_SEPARATOR . "*.php") as $matcherFile) {
            $this->_matchers[basename($matcherFile, ".php")] = $nameSpace;
        }

        foreach (glob($currentPath . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR) as $appendPath) {
            $this->_recursivelyRegisterMatchersOnFolder($originalPath . DIRECTORY_SEPARATOR . basename($appendPath));
        }
    }

    private function _fromPathToNamespace($path)
    {
        $nameSpace = str_replace(DIRECTORY_SEPARATOR, self::NAMESPACE_SEPARATOR, $path);
        if (substr($nameSpace, -1) !== self::NAMESPACE_SEPARATOR) {
            $nameSpace .= self::NAMESPACE_SEPARATOR;
        }
        return $nameSpace;
    }

    private function _findMatcherPath($path)
    {
        $includePaths = explode(PATH_SEPARATOR, get_include_path());
        foreach ($includePaths as $child) {
            if (is_dir($child . DIRECTORY_SEPARATOR . $path)) {
                return $child . DIRECTORY_SEPARATOR . $path;
            }
        }
    }
}
