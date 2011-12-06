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
        $formatter->update($this->_reporter, ReporterEvent::newWithTimeAndName('start', time(), 'example1'));
        $this->_formatter = $formatter;

        $this->_doc = new \SimpleXMLElement('<testsuites></testsuites>');
    }
    
    public function itFormatsErrorsInJunitFormat() {
        $formatter = $this->_formatter;
        $formatter->update($this->_reporter, new ReporterEvent('status', '.', 'example1'));
        
        $actual = $this->_formatter->output();
        
        $expected = $this->_doc;
        $suite = $expected->addChild('testsuite');
        $suite->addAttribute('class', 'example1');
        $suite->addAttribute('name', 'example1');
        
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