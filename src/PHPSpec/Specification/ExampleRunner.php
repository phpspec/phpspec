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

class ExampleRunner
{
    protected $_exampleFactory;
    protected $_interceptorFactory;
    
    public function run(ExampleGroup $exampleGroup, Reporter $reporter)
    {
        $reporter->exampleGroupStarted($exampleGroup);
        $this->runExamples($exampleGroup, $reporter);
        $reporter->exampleGroupFinished($exampleGroup);
    }
    
    protected function runExamples(ExampleGroup $exampleGroup, Reporter $reporter)
    {
        $object = new \ReflectionObject($exampleGroup);
        foreach ($object->getMethods() as $method) {
            $name = $method->getName();
            if (strtolower(substr($name, 0, 2)) === 'it') {
                $this->createExample($exampleGroup, $method)->run($reporter);
            }
        }
    }
    
    protected function createExample(ExampleGroup $exampleGroup, \ReflectionMethod $example)
    {
        return $this->getExampleFactory()->create($exampleGroup, $example);
    }
    
    public function getExampleFactory()
    {
        if ($this->_exampleFactory === null) {
            $this->_exampleFactory = new ExampleFactory;
        }
        return $this->_exampleFactory;
    }
    
    public function setExampleFactory(ExampleFactory $factory)
    {
        $this->_exampleFactory = $factory;
    }
}