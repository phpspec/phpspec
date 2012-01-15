<?php

namespace Spec\PHPSpec\Runner\Formatter;

use PHPSpec\Runner\ReporterEvent;

use PHPSpec\Runner\Cli\Reporter;

use PHPSpec\Runner\Formatter\Junit;

class DescribeJunit extends \PHPSpec\Context {
    
    private $_reporter;
    private $_formatter;
    private $_doc;
    
    public function before() {
        $this->_reporter = $this->mock('PHPSpec\Runner\Cli\Reporter');
        
        $formatter = new Junit($this->_reporter);
        $formatter->update($this->_reporter, ReporterEvent::newWithTimeAndName('start', time(), 'Dummy'));
        $this->_formatter = $formatter;

        $this->_doc = new \SimpleXMLElement('<testsuites></testsuites>');
    }
    
    public function itFormatsPassesInJunitFormat() {
        $msg = 'The message. Doesn\'t matter what it is as long as it is shown in the failure';
        $failure_e = new \Exception($msg);
        $formatter = $this->_formatter;
        $formatter->update($this->_reporter, new ReporterEvent(
        	'status',
        	'.',
        	'example1',
            '',
            '',
            null,
            '0.01',
            '2'
        ));
        $formatter->update($this->_reporter, new ReporterEvent('finish', '', 'Dummy'));
        
        $actual = $this->_formatter->output();
        
        $expected = $this->_doc;
        $suite = $expected->addChild('testsuite');
        $suite->addAttribute('name', 'Dummy');
        
        $suite->addAttribute('tests', '1');
        $suite->addAttribute('failures', '0');
        $suite->addAttribute('errors', '0');
        $suite->addAttribute('time', '0.01');
        $suite->addAttribute('assertions', '2');
        
        $case = $suite->addChild('testcase');
        $case->addAttribute('class', 'Dummy');
        $case->addAttribute('name', 'example1');
        $case->addAttribute('time', '0.01');
        $case->addAttribute('assertions', '2');
        
        $this->spec($actual->asXml())
            ->should->be($expected->asXml());
    }
    
    public function itFormatsPendingInJunitFormat() {
        $msg = 'The message. Doesn\'t matter what it is as long as it is shown in the failure';
        $failure_e = new \Exception($msg);
        $formatter = $this->_formatter;
        $formatter->update($this->_reporter, new ReporterEvent(
        	'status',
        	'*',
        	'example1',
            $failure_e->getMessage(),
            $failure_e->getTraceAsString(),
            $failure_e,
            '0.01',
            '2'
        ));
        $formatter->update($this->_reporter, new ReporterEvent('finish', '', 'Dummy'));
        
        $actual = $this->_formatter->output();
        
        $expected = $this->_doc;
        $suite = $expected->addChild('testsuite');
        $suite->addAttribute('name', 'Dummy');
        
        $suite->addAttribute('tests', '1');
        $suite->addAttribute('failures', '1');
        $suite->addAttribute('errors', '0');
        $suite->addAttribute('time', '0.01');
        $suite->addAttribute('assertions', '2');
        
        $case = $suite->addChild('testcase');
        $case->addAttribute('class', 'Dummy');
        $case->addAttribute('name', 'example1');
        $case->addAttribute('time', '0.01');
        $case->addAttribute('assertions', '2');
        
        $failure_msg = PHP_EOL . 'example1 (PENDING)' . PHP_EOL;
        $failure_msg .= $msg . PHP_EOL;
        
        $fail = $case->addChild('failure', $failure_msg);
        $fail->addAttribute('type', 'Exception');
        
        $this->spec($actual->asXml())
            ->should->be($expected->asXml());
    }
    
    public function itFormatsFailuresInJunitFormat() {
        $msg = 'The message. Doesn\'t matter what it is as long as it is shown in the failure';
        $failure_e = new \Exception($msg);
        $formatter = $this->_formatter;
        $formatter->update($this->_reporter, new ReporterEvent(
        	'status',
        	'F',
        	'example1',
            $failure_e->getMessage(),
            $failure_e->getTraceAsString(),
            $failure_e,
            '0.01',
            '2'
        ));
        $formatter->update($this->_reporter, new ReporterEvent('finish', '', 'Dummy'));
        
        $actual = $this->_formatter->output();
        
        $expected = $this->_doc;
        $suite = $expected->addChild('testsuite');
        $suite->addAttribute('name', 'Dummy');
        
        $suite->addAttribute('tests', '1');
        $suite->addAttribute('failures', '1');
        $suite->addAttribute('errors', '0');
        $suite->addAttribute('time', '0.01');
        $suite->addAttribute('assertions', '2');
        
        $case = $suite->addChild('testcase');
        $case->addAttribute('class', 'Dummy');
        $case->addAttribute('name', 'example1');
        $case->addAttribute('time', '0.01');
        $case->addAttribute('assertions', '2');
        
        $failure_msg = PHP_EOL . 'example1 (FAILED)' . PHP_EOL;
        $failure_msg .= $msg . PHP_EOL;
        $failure_msg .= $failure_e->getTraceAsString() . PHP_EOL;
        
        $fail = $case->addChild('failure', $failure_msg);
        $fail->addAttribute('type', 'Exception');
        
        $this->spec($actual->asXml())
            ->should->be($expected->asXml());
    }
    
    public function itFormatsErrorsInJunitFormat() {
        $msg = 'The message. Doesn\'t matter what it is as long as it is shown in the failure';
        $failure_e = new \Exception($msg);
        $formatter = $this->_formatter;
        $formatter->update($this->_reporter, new ReporterEvent(
            'status',
            'E',
            'example1',
            $failure_e->getMessage(),
            $failure_e->getTraceAsString(),
            $failure_e,
            '0.01',
            '2'
        ));
        $formatter->update($this->_reporter, new ReporterEvent('finish', '', 'Dummy'));
        
        $actual = $this->_formatter->output();
        
        $expected = $this->_doc;
        $suite = $this->_createSuite($expected, 'Dummy', 1, 0, 1, '0.01', 2);
        $case = $this->_createCase($suite, 'Dummy', 'example1', '0.01', 2);
        
        $failure_msg = PHP_EOL . 'example1 (ERROR)' . PHP_EOL;
        $failure_msg .= $msg . PHP_EOL;
        $failure_msg .= $failure_e->getTraceAsString() . PHP_EOL;
        
        $fail = $case->addChild('error', $failure_msg);
        $fail->addAttribute('type', 'Exception');
        
        $this->spec($actual->asXml())
            ->should->be($expected->asXml());
    }
    
    private function _createSuite($doc, $name, $tests, $failures, $errors,
                                  $time, $assertions)
    {
        $suite = $doc->addChild('testsuite');
        $suite->addAttribute('name', $name);
        
        $suite->addAttribute('tests', $tests);
        $suite->addAttribute('failures', $failures);
        $suite->addAttribute('errors', $errors);
        $suite->addAttribute('time', $time);
        $suite->addAttribute('assertions', $assertions);
        
        return $suite;
    }
    
    public function _createCase($suite, $class, $example, $time, $assertions)
    {
        $case = $suite->addChild('testcase');
        $case->addAttribute('class', $class);
        $case->addAttribute('name', $example);
        $case->addAttribute('time', $time);
        $case->addAttribute('assertions', $assertions);
        
        return $case;
    }
    
    private function _buildExpectation($expected) {
        $output = $this->_formatStart;
        $output .= $expected;
        $output .= $this->_formatEnd;
        
        return $output;
    }
    
}