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
    
    public function itFormatsErrorsInJunitFormat() {
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
            '0.1'
        ));
        
        $actual = $this->_formatter->output();
        
        $expected = $this->_doc;
        $suite = $expected->addChild('testsuite');
        $suite->addAttribute('name', 'Dummy');
        
        $case = $suite->addChild('testcase');
        $case->addAttribute('class', 'Dummy');
        $case->addAttribute('name', 'example1');
        
        $failure_msg = PHP_EOL . 'example1 (FAILED)' . PHP_EOL;
        $failure_msg .= $msg . PHP_EOL;
        $failure_msg .= $failure_e->getTraceAsString() . PHP_EOL;
        
        $fail = $case->addChild('failure', $failure_msg);
        $fail->addAttribute('type', 'Exception');
        
        $this->spec($actual->asXml())
            ->should->be($expected->asXml());
    }
    
    private function _buildExpectation($expected) {
        $output = $this->_formatStart;
        $output .= $expected;
        $output .= $this->_formatEnd;
        
        return $output;
    }
    
}