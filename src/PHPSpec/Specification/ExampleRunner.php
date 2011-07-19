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
namespace PHPSpec\Specification;

use \PHPSpec\Runner\Reporter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class ExampleRunner
{
    /**
     * The example factory
     *
     * @var PHPSpec\Specification\ExampleFactory
     */
    protected $_exampleFactory;
    
    /**
     * Pattern of the name of the example to run
     *
     * @var string
     */
    protected $_runOnly = false;
    
    /**
     * Runs all examples inside an example group
     * 
     * @param PHPSpec\Specification\ExampleGroup $exampleGroup
     * @param \PHPSpec\Runner\Reporter           $reporter
     */
    public function run(ExampleGroup $exampleGroup, Reporter $reporter)
    {
        $reporter->exampleGroupStarted($exampleGroup);
        $this->runExamples($exampleGroup, $reporter);
        $reporter->exampleGroupFinished($exampleGroup);
    }
    
    /**
     * Creates and runs all examples (methods started with 'it')
     * 
     * @param PHPSpec\Specification\ExampleGroup $exampleGroup
     * @param \PHPSpec\Runner\Reporter           $reporter
     */
    protected function runExamples(ExampleGroup $exampleGroup,
                                   Reporter $reporter)
    {
        $object = new \ReflectionObject($exampleGroup);
        foreach ($object->getMethods() as $method) {
            $name = $method->getName();
            if (strtolower(substr($name, 0, 2)) === 'it') {
                if ($this->_runOnly &&
                    preg_match("/$this->_runOnly/", $name)) {
                    $this->createExample($exampleGroup, $method)
                         ->run($reporter);
                    break;
                }
                if (!$this->_runOnly) {
                    $this->createExample($exampleGroup, $method)
                         ->run($reporter);
                }
            }
        }
    }
    
    /**
     * Creates an example
     * 
     * @param PHPSpec\Specification\ExampleGroup $exampleGroup
     * @param \ReflectionMethod                  $example
     * @return \PHPSpec\Specification\Example
     */
    protected function createExample(ExampleGroup $exampleGroup,
                                     \ReflectionMethod $example)
    {
        return $this->getExampleFactory()->create($exampleGroup, $example);
    }
    
    /**
     * Gets the example factory
     * 
     * @return PHPSpec\Specification\ExampleFactory
     */
    public function getExampleFactory()
    {
        if ($this->_exampleFactory === null) {
            $this->_exampleFactory = new ExampleFactory;
        }
        return $this->_exampleFactory;
    }
    
    /**
     * Sets the example factory
     * 
     * @param PHPSpec\Specification\ExampleFactory $factory
     */
    public function setExampleFactory(ExampleFactory $factory)
    {
        $this->_exampleFactory = $factory;
    }
    
    /**
     * Sets the runner to run only a single example
     *
     * @param string $example
     */
    public function runOnly($example)
    {
        $this->_runOnly = $example;
    }
}