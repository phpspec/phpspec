<?php

namespace Spec\PHPSpec\Runner\Formatter;

use PHPSpec\Runner\ReporterEvent;

use PHPSpec\Runner\Cli\Reporter;

use PHPSpec\Runner\Formatter\Junit;

class DescribeJunit extends \PHPSpec\Context {
    
    private $_reporter;
    private $_formatter;
    private $_doc;
    private $_msg = 'The message. Doesn\'t matter what it is as long as it is
                    shown in the failure';
    
    public function before() {
        $this->_reporter = $this->mock('PHPSpec\Runner\Cli\Reporter');
        
        $formatter = new Junit($this->_reporter);
        $formatter->update($this->_reporter, ReporterEvent::newWithTimeAndName('start', time(), 'Dummy'));
        $this->_formatter = $formatter;

        $this->_doc = new \SimpleXMLElement('<testsuites></testsuites>');
    }
    
    public function itFormatsPassesInJunitFormat() {
        $this->_updateFormatterWithException(
            '.',
            'example1',
            null,
            '0.01',
            '2'
        );
        $this->_finishSuite();
        
        $suite = $this->_createSuite('Dummy', 1, 0, 0, '0.01', 2);
        $case = $this->_createCase($suite, 'Dummy', 'example1', '0.01', 2);

        $this->_compare();
    }
    
    public function itFormatsPendingInJunitFormat() {
        $failure_e = $this->_getFailureException();

        $this->_updateFormatterWithException(
            '*',
            'example1',
            $failure_e,
            '0.01',
            '2'
        );
        $this->_finishSuite();
        
        $suite = $this->_createSuite('Dummy', 1, 1, 0, '0.01', 2);
        $case = $this->_createCase($suite, 'Dummy', 'example1', '0.01', 2);
        // @todo try to change the type to Failure or something else
        $fail = $this->_createFailure($case, 'example1', $failure_e, '*');

        $this->_compare();
    }
    
    public function itFormatsFailuresInJunitFormat() {
        $failure_e = $this->_getFailureException();

        $this->_updateFormatterWithException(
            'F',
            'example1',
            $failure_e,
            '0.01',
            '2'
        );
        $this->_finishSuite();
        
        $suite = $this->_createSuite('Dummy', 1, 1, 0, '0.01', 2);
        $case = $this->_createCase($suite, 'Dummy', 'example1', '0.01', 2);
        // @todo try to change the type to Failure or something else
        $fail = $this->_createFailure($case, 'example1', $failure_e, 'F');

        $this->_compare();
    }
    
    public function itFormatsErrorsInJunitFormat() {
        $failure_e = $this->_getFailureException();
        $this->_updateFormatterWithException(
            'E',
            'example1',
            $failure_e,
            '0.01',
            '2'
        );
        $this->_finishSuite();
        
        $suite = $this->_createSuite('Dummy', 1, 0, 1, '0.01', 2);
        $case = $this->_createCase($suite, 'Dummy', 'example1', '0.01', 2);
        $fail = $this->_createFailure($case, 'example1', $failure_e, 'E');

        $this->_compare();
    }

    private function _createFailure($case, $example, \Exception $e, $type) {
        switch ($type) {
        case 'E':
            $error_type = 'ERROR';
            $tag = 'error';
            break;
        case 'F':
            $error_type = 'FAILED';
            $tag = 'failure';
            break;
        case '*':
            $error_type = 'PENDING';
            $tag = 'failure';
            break;
        default:
            throw new \Exception("Invalid type $type");
        }
        $failure_msg = $this->_generateFailureMessage('example1', $this->_msg, $e, $error_type);

        $fail = $case->addChild($tag, $failure_msg);
        $fail->addAttribute('type', 'Exception');
    }

    private function _getFailureException() {
        $failure_e = new \Exception($this->_msg);

        return $failure_e;
    }

    private function _generateFailureMessage($name, $msg, \Exception $exception, $type) {
        $failure_msg = PHP_EOL . "$name ($type)" . PHP_EOL;
        $failure_msg .= $msg . PHP_EOL;

        if ($type != 'PENDING') {
            $failure_msg .= $exception->getTraceAsString() . PHP_EOL;
        }

        return $failure_msg;
    }

    private function _updateFormatterWithException($status, $example,
        $exception=null, $time, $assertions) {
        if (is_null($exception)) {
            $this->_formatter->update($this->_reporter, new ReporterEvent(
                'status',
                $status,
                $example,
                '',
                '',
                null,
                $time,
                $assertions
            ));
        } else {
            $this->_formatter->update($this->_reporter, new ReporterEvent(
                'status',
                $status,
                $example,
                $exception->getMessage(),
                $exception->getTraceAsString(),
                $exception,
                $time,
                $assertions
            ));
        }
    }

    private function _updateFormatter($status, $example, $message,
        $traceString, $exception, $time, $assertions) {
        $this->_formatter->update($this->_reporter, new ReporterEvent(
            'status',
            $status,
            $example,
            $message,
            $traceString,
            $exception,
            $time,
            $assertions
        ));
    }

    private function _compare() {
        $this->spec($this->_formatter->output()->asXml())
            ->should->be($this->_doc->asXml());
    }

    private function _finishSuite() {
        $this->_formatter->update($this->_reporter, new ReporterEvent('finish', '', 'Dummy'));
    }
    
    private function _createSuite($name, $tests, $failures, $errors,
                                  $time, $assertions)
    {
        $suite = $this->_doc->addChild('testsuite');
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