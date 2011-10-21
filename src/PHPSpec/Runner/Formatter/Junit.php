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
 * Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 *
 *
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite
  	name="TestDummy"
  	file="/home/mmueller/Development/Checkouts/trivago-php/tests/unit/TestDummy.php"
  	tests="1" assertions="1" failures="0" errors="0" time="0.005316">
    <testcase name="testNothing" class="TestDummy"
      file="/home/mmueller/Development/Checkouts/trivago-php/tests/unit/TestDummy.php"
      line="12" assertions="1" time="0.005316"/>
  </testsuite>
</testsuites>
 */
namespace PHPSpec\Runner\Formatter;
use PHPSpec\Util\Backtrace, PHPSpec\Specification\Result\DeliberateFailure, PHPSpec\Runner\Reporter;
class Junit extends Progress
{
    /**
     * @var \SimpleXMLElement
     */
    private $_xml;
    private $_i = 0;
    public function __construct (Reporter $reporter)
    {
        parent::__construct($reporter);
        $this->_xml = new \SimpleXMLElement("<testsuites></testsuites>");
    }
    /**
     * Prints the report in a specific format
     */
    public function output ()
    {
        $this->_xml->asXML('junit.xml');
    }
    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Receive update from subject
     * @link http://php.net/manual/en/splobserver.update.php
     * @param \PHPSpec\Runner\Reporter $subject <p>
     * The SplSubject notifying the observer of an update.
     * </p>
     * @return void
     */
    public function update (\SplSubject $subject)
    {
        $passed = $subject->getPassing();
        $failes = $subject->getFailures();
        $errors = $subject->getErrors();
        $sumOfAllTests = sizeof($passed) + sizeof($failes) + sizeof($errors);
        $suites = array();
        foreach ($passed as $pass) {
            /* @var $pass \PHPSpec\Specification\Example */
            $exampleGroupName = get_class($pass->getExampleGroup());
            if (! array_key_exists($exampleGroupName, $suites)) {
                $suites[$exampleGroupName] = array();
            }
            print $pass->getSpecificationText() . PHP_EOL;
            print $pass->getDescription() . PHP_EOL;
            print PHP_EOL;
        }
        foreach ($failes as $fail) {
            /* @var $pass \PHPSpec\Specification\Example */
            $exampleGroupName = get_class($pass->getExampleGroup());
            if (! array_key_exists($exampleGroupName, $suites)) {
                $suites[$exampleGroupName] = array();
            }
            print $pass->getSpecificationText() . PHP_EOL;
            print $pass->getDescription() . PHP_EOL;
            print PHP_EOL;
        }
        foreach ($errors as $error) {
            /* @var $pass \PHPSpec\Specification\Example */
            $exampleGroupName = get_class($pass->getExampleGroup());
            if (! array_key_exists($exampleGroupName, $suites)) {
                $suites[$exampleGroupName] = array();
            }
            print $pass->getSpecificationText() . PHP_EOL;
            print $pass->getDescription() . PHP_EOL;
            print PHP_EOL;
        }
    }
    /**
     * @return SimpleXMLElement
     * <testcase name="testNothing" class="TestDummy"
     * file="/home/mmueller/Development/Checkouts/trivago-php/tests/unit/TestDummy.php"
     * line="12" assertions="1" time="0.005316"/>
     */
    private function createCase (\SimpleXMLElement $suite, $name, $class, $file, 
    $line, $assertions, $executionTime)
    {
        $child = $suite->addChild('testcase');
        $child->addAttribute('name', $name);
        $child->addAttribute('class', $class);
        $child->addAttribute('file', $file);
        $child->addAttribute('line', $line);
        $child->addAttribute('assertions', $assertions);
        $child->addAttribute('executionTime', $executionTime);
        return $child;
    }
    /**
     * @param $name
     * @param $file
     * @param $testcount
     * @param $assertions
     * @param $failures
     * @param $errors
     * @param $executionTime
     * @return SimpleXMLElement
     * <testsuite name="TestDummy"
     *   file="/home/mmueller/Development/Checkouts/trivago-php/tests/unit/TestDummy.php"
     *   tests="1" assertions="1" failures="0" errors="0" time="0.005316">
     */
    private function createSuite ($name, $file, $testcount, $assertions, 
    $failures, $errors, $executionTime)
    {
        $testSuite = $this->_xml->addChild("testsuite");
        $testSuite->addAttribute("name", $name);
        $testSuite->addAttribute("file", $file);
        $testSuite->addAttribute("tests", $testcount);
        $testSuite->addAttribute("assertions", $assertions);
        $testSuite->addAttribute("failures", $failures);
        $testSuite->addAttribute("errors", $errors);
        $testSuite->addAttribute("time", $executionTime);
        return $testSuite;
    }
}
