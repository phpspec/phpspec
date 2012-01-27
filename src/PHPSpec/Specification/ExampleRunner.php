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
namespace PHPSpec\Specification;

use \PHPSpec\Runner\Reporter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class ExampleRunner
{
    const ALL_EXAMPLES = '.*';
    
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
    protected $_examplesToRun = ExampleRunner::ALL_EXAMPLES;
    
    /**
     * Example groups that have started (only when -e is used)
     *
     * @var array
     */
    protected $_groupsStarted = array();

    /**
     * Example groups that have finished (only when -e is used)
     *
     * @var array
     */
    protected $_groupsFinished = array();
    
    /**
     * Runs all examples inside an example group
     * 
     * @param PHPSpec\Specification\ExampleGroup $exampleGroup
     * @param \PHPSpec\Runner\Reporter           $reporter
     */
    public function run(ExampleGroup $exampleGroup, Reporter $reporter)
    {
        if ($this->_examplesToRun !== ExampleRunner::ALL_EXAMPLES) {
            $this->checkGroupStarted($exampleGroup, $reporter);
            $this->runExamples($exampleGroup, $reporter);
            $this->checkGroupFinished($exampleGroup, $reporter);
            return;
        }

        $reporter->exampleGroupStarted($exampleGroup);
        $this->runExamples($exampleGroup, $reporter);
        $reporter->exampleGroupFinished($exampleGroup);
    }
    
    /**
     * Checks if example group has started, if it hasn't then it will notify
     * the reporter that it has
     */
    private function checkGroupStarted(ExampleGroup $exampleGroup,
                                       Reporter $reporter)
    {
        $groupName = get_class($exampleGroup);
        foreach ($this->getMethodNames($exampleGroup) as $method) {
            if ($this->methodIsAnExample($method) &&
                $this->filterExample($method) &&
                $this->groupHasntStarted($groupName)) {
                $reporter->exampleGroupStarted($exampleGroup);
                $this->_groupsStarted[] = $groupName;
            }
        }
    }
    
    /**
     * Checks if example group has finished, if it hasn't then it will notify
     * the reporter that it has
     */
    private function checkGroupFinished(ExampleGroup $exampleGroup,
                                       Reporter $reporter)
    {
        $groupName = get_class($exampleGroup);
        foreach ($this->getMethodNames($exampleGroup) as $method) {
            if ($this->methodIsAnExample($method) &&
                $this->filterExample($method) &&
                $this->groupHasntFinished($groupName)) {
                $reporter->exampleGroupFinished($exampleGroup);
                $this->_groupsFinished[] = $groupName;
            }
        }
    }
    
    /**
     * Whether the example group has had any example ran
     *
     * @param string $exampleGroup
     * @return boolean
     */
    private function groupHasntStarted($exampleGroup)
    {
        return !in_array($exampleGroup, $this->_groupsStarted);
    }
    
    /**
     * Whether the example group has finished running the examples
     *
     * @param string $exampleGroup
     * @return boolean
     */
    private function groupHasntFinished($exampleGroup)
    {
        return !in_array($exampleGroup, $this->_groupsFinished);
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
        foreach ($this->getMethodNames($exampleGroup) as $methodName) {
            if ($this->methodIsAnExample($methodName) &&
                $this->filterExample($methodName)) {
                $this->createExample($exampleGroup, $methodName)
                     ->run($reporter);
            }
        }
    }
    
    /**
     * Gets the example group method names
     *
     * @param ExampleGroup $exampleGroup 
     * @return array
     */
    private function getMethodNames(ExampleGroup $exampleGroup)
    {
        $object = new \ReflectionObject($exampleGroup);
        $methodNames = array();
        foreach ($object->getMethods() as $method) {
            $methodNames[] = $method->getName();
        }
        return $methodNames;
    }
    
    /**
     * Whether the method name starts with it, indicating it is an example
     *
     * @param string $name 
     * @return boolean
     */
    private function methodIsAnExample($name)
    {
        return strtolower(substr($name, 0, 2)) === 'it';
    }
    
    /**
     * If I am filtering examples with the -e|--example flag this will return
     * true if the current example matches the filter, causing the example to
     * run
     *
     * @param string $name 
     * @return boolean
     */
    private function filterExample($name)
    {
        return preg_match("/$this->_examplesToRun/i", $name);
    }
    
    /**
     * Creates an example
     * 
     * @param PHPSpec\Specification\ExampleGroup $exampleGroup
     * @param string                             $example
     * @return \PHPSpec\Specification\Example
     */
    protected function createExample(ExampleGroup $exampleGroup, $example)
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
        $this->_examplesToRun = $example;
    }
}