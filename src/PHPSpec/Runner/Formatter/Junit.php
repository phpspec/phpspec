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
namespace PHPSpec\Runner\Formatter;

use PHPSpec\Util\Backtrace,
    PHPSpec\Specification\Result\DeliberateFailure,
    PHPSpec\Runner\Reporter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @author     Mario Mueller <mario.mueller@xenji.com>
 * @author     Amjad Mohamed <amjad@alliedinsure.net>
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 * @since      File available since release 1.3.0
 */
class Junit extends Progress
{
    
    /**
     * @var \SimpleXMLElement
     */
    private $_xml;
    
    /**
     * Final output
     *
     * @var string
     */
    private $_result;
    
    /**
     * Example elements
     *
     * @var string
     */
    private $_examples;
    
    /**
     * Current example group
     *
     * @var string
     */
    private $_currentGroup;
    
    /**
     * Number of errors
     *
     * @var integer
     */
    private $_errors = 0;
    
    /**
     * Number of failures
     *
     * @var integer
     */
    private $_failures = 0;
    
    /**
     * Number of pending
     *
     * @var integer
     */
    private $_pending = 0;
    
    /**
     * Total of examples
     *
     * @var string
     */
    private $_total = 0;
    
    /**
     * Tell building tool to error with status > 0
     *
     * @var boolean
     */
    private $_errorOnExit = false;

    /**
     * Creates the formatter adding a testsuites root to the xml
     */
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
        $output = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $output .= '<testsuites>' . PHP_EOL . $this->_result;
        $output .= '</testsuites>' . PHP_EOL;
        echo $output;
    }
    
    /**
     * Opens the testsuite tag
     * @see FormatterAbstract::_startRenderingExampleGroup()
     *
     * @param PHPSpec\Runnner\ReporterEvent $reporterEvent
     */
    protected function _startRenderingExampleGroup($reporterEvent)
    {
        static $groupIndex = 1;
        
        $this->_currentGroup = $reporterEvent->example;
    }
    
    /**
     * Finishes rendering an example group
     */
    protected function _finishRenderingExampleGroup()
    {
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
    }
    
    /**
     * Render examples
     *
     * @param PHPSpec\Runnner\ReporterEvent $reporterEvent
     */
    protected function _renderExamples($reporterEvent)
    {
        $this->_total++;
        
        $status = $reporterEvent->status;
        
        $output = '  <testcase class="' . $this->_currentGroup . '"';
        $output .= ' name="' . $reporterEvent->example . '"';
        // $output .= ' file="filename.php"'; not available yet
        // $output .= ' assertions="1"'; not available yet
        // $output .= ' time="0.01"'; not available yet
        // $output .= ' line="30"'; not available yet
        
        switch ($status) {
            case '.':
                $output .= ' />' . PHP_EOL;
                $this->_examples .= $output;
                break;
            case '*':
                $error = '   <error type="';
                $error .= get_class($reporterEvent->exception) . '">';
                $error .= PHP_EOL;
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
                $error = '   <error type="';
                $error .= get_class($reporterEvent->exception).'">' . PHP_EOL;
                $error .= '    ' . $reporterEvent->example . '(FAILED)';
                $error .= PHP_EOL;
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
                $error = '   <failure type="';
                $error .= get_class($reporterEvent->exception).'">' . PHP_EOL;
                
                $error .= '    ' . $reporterEvent->example . '(FAILED)';
                $error .= PHP_EOL;
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
     * Cleans and returns a line. Removes php tag added to make
     * highlight-string work
     * 
     * @param array   $traceline
     * @param integer $relativePosition
     * @param string  $style
     * @return string
     */
    protected function getLine($traceline, $relativePosition,
                               $style = 'normal')
    {
        $code = Backtrace::readLine(
            $traceline['file'],
            $traceline['line'] + $relativePosition
        );
        return '    ' . $code . PHP_EOL;
    }
    
    /**
     * Exits with status > 0 if there were errors in the specs 
     */
    protected function _onExit()
    {
        if ($this->_errorOnExit) {
            exit(1);
        }
        exit(0);
    } 
    
    /**
     * Creates a testcase
     *
     * @param \SimpleXMLElement $suite
     * @param string $name
     * @param string $class
     * @param string $file
     * @param integer $line
     * @param string $assertions
     * @param integer $executionTime
     *
     * @return SimpleXMLElement
     * <testcase name="testNothing" class="TestDummy"
     * file="/home/mmueller/dev/trivago-php/tests/unit/TestDummy.php"
     * line="12" assertions="1" time="0.005316"/>
     */
    private function createCase (\SimpleXMLElement $suite, $name, $class,
                                 $file, $line, $assertions, $executionTime)
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
     * Creates suite 
     *
     * @param $name
     * @param $file
     * @param $testcount
     * @param $assertions
     * @param $failures
     * @param $errors
     * @param $executionTime
     *
     * @return SimpleXMLElement
     * <testsuite name="TestDummy"
     *   file="/home/mmueller/dev/trivago-php/tests/unit/TestDummy.php"
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
