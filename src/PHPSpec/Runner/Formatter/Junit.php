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
    private $_result;
    private $_examples;
    private $_currentGroup;
    private $_errors = 0;
    private $_failures = 0;
    private $_pending = 0;
    private $_total = 0;
    private $_complete = 0;
    private $_errorOnExit = false;
    
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
        return $this->_xml;
    }
    
    /**
     * Opens the testsuite tag
     * @see PHPSpec\Runner\Formatter\FormatterAbstract::_startRenderingExampleGroup()
     */
    protected function _startRenderingExampleGroup($reporterEvent)
    {
        static $groupIndex = 1;
        
        $this->_testSuite = $this->_xml->addChild('testsuite');
        $this->_testSuite->addAttribute('name', $reporterEvent->example);
        
        $this->_currentGroup = $reporterEvent->example;
    }
    
    protected function _finishRenderingExampleGroup()
    {
        $
        
        $output = ' <testsuite name="'.$this->_currentGroup.'" ';
        $output .= 'tests="' . $this->_total . '" ';
        // $output .= 'assertions="' . $this->_total . '" '; not available yet
        $output .= 'failures="' . $this->_failures . '" ';
        $output .= 'errors="' . $this->_errors . '" ';
        // $output .= 'time="0.01" '; not available yet
        $output .= '>' . PHP_EOL;
        $output .= $this->_examples;
        $output .= ' </testsuite>' . PHP_EOL;
        $this->_result .= $output;
        $this->_examples = '';
        
        if ($this->_errors > 0 || $this->_failures > 0) {
            $this->_errorOnExit = true;
        }
        
        $this->_total = 0;
        $this->_failures = 0;
        $this->_errors = 0;
        $this->_pending = 0;
        $this->_complete = 0;
    }
    
    protected function _renderExamples($reporterEvent)
    {
        $this->_total++;
        
        $status = $reporterEvent->status;
        
        $case = $this->_testSuite->addChild('testcase');
        $case->addAttribute('class', $this->_currentGroup);
        $case->addAttribute('name', $reporterEvent->example);
        
        $output = '  <testcase class="' . $this->_currentGroup . '"';
        $output .= ' name="' . $reporterEvent->example . '"';
        // $output .= ' file="filename.php"'; not available yet
        // $output .= ' assertions="1"'; not available yet
        // $output .= ' time="0.01"'; not available yet
        // $output .= ' line="30"'; not available yet
        
        switch ($status) {
            case '.':
                $output .= ' />' . PHP_EOL;
                
                $this->_complete++;
                break;
            case '*':
                $error = '   <error type="'.get_class($reporterEvent->exception).'">' . PHP_EOL;
                $error .= '    Skipped Test: ' . $reporterEvent->example;
                $error .= '    ' . $reporterEvent->message;
                $error .= '   </error>';
                
                $output .= '>' . PHP_EOL;
                $output .= $error . PHP_EOL;
                $output .= '  </testcase>' . PHP_EOL;
                $this->_examples .= $output;
                
                $this->_errors++;
                break;
            case 'E':
                $error = '   <error type="'.get_class($reporterEvent->exception).'">' . PHP_EOL;
                $error .= '    ' . $reporterEvent->example . '(FAILED)' . PHP_EOL;
                $error .= '    ' . $reporterEvent->message . PHP_EOL;
                $error .= '    ' . $reporterEvent->backtrace . PHP_EOL;
                $error .= $this->getCode($reporterEvent->exception) . PHP_EOL;
                
                $error .= '   </error>';
                
                $output .= '>' . PHP_EOL;
                $output .= $error . PHP_EOL;
                $output .= '  </testcase>' . PHP_EOL;
                $this->_examples .= $output;
                
                $this->_errors++;
                break;
            case 'F':
                $failure_msg = PHP_EOL . $reporterEvent->example . ' (FAILED)' . PHP_EOL;
                $failure_msg .= $reporterEvent->message . PHP_EOL;
                $failure_msg .= $reporterEvent->backtrace . PHP_EOL;
                
                $failure = $case->addChild('failure', $failure_msg);
                $failure->addAttribute('type', get_class($reporterEvent->exception));
                
                $error = '   <failure type="'.get_class($reporterEvent->exception).'">' . PHP_EOL;
                
                $error .= '    ' . $reporterEvent->example . '(FAILED)' . PHP_EOL;
                $error .= '    ' . $reporterEvent->message . PHP_EOL;
                $error .= '    ' . $reporterEvent->backtrace . PHP_EOL;
                $error .= $this->getCode($reporterEvent->exception) . PHP_EOL;
                
                $error .= '   </failure>';
                
                $output .= '>' . PHP_EOL;
                $output .= $error . PHP_EOL;
                $output .= '  </testcase>' . PHP_EOL;
                $this->_examples .= $output;
                
                $this->_failures++;
                break;
        }
    }
    
    /**
     * Gets the code based on the exception backtrace
     * 
     * @param \Exception $e
     * @return string
     */
    protected function getCode($e)
    {
        if (!$e instanceof \Exception) {
            return '';
        }
        
        if (!$e instanceof \PHPSpec\Specification\Result\DeliberateFailure) {
            $traceline = Backtrace::getFileAndLine($e->getTrace(), 1);
        } else {
            $traceline = Backtrace::getFileAndLine($e->getTrace());
        }
        $lines = '';
        if (!empty($traceline)) {
            $lines .= $this->getLine($traceline, -2);
            $lines .= $this->getLine($traceline, -1);
            $lines .= $this->getLine($traceline, 0, 'offending');
            $lines .= $this->getLine($traceline, 1);
        }
        
        return $lines;
    }

    /**
     * Cleans and returns a line. Removes php tag added to make highlight-string
     * work
     * 
     * @param unknown_type $traceline
     * @param unknown_type $relativePosition
     * @param unknown_type $style
     * @return Ambigous <string, mixed>
     */
    protected function getLine($traceline, $relativePosition, $style = 'normal')
    {
        $code = Backtrace::readLine(
            $traceline['file'],
            $traceline['line'] + $relativePosition
        );
        return '    ' . $code . PHP_EOL;
    }
    
    protected function _onExit()
    {
        if ($this->_errorOnExit) {
            exit(1);
        }
        exit(0);
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
