<?php

namespace Spec\PHPSpec\Runner\Formatter;

use PHPSpec\Runner\Formatter\Textmate as TextmateFormatter;

class DescribeTextmate extends \PHPSpec\Context
{
    const TEXTMATE_URL = 'txmt://open?url=file://%s&line=%s';
    
    public function itDecoratesTheBacktraceLinesWithATextmateLink()
    {
        $reporter = $this->_buildReporter();
        $reportEvent = $this->_buildReportEvent();
        
        $reportEvent->backtrace = '    # ./_files/spec/FooSpec.php:8';
        
        $textmateFormatter = new TextmateFormatter($reporter);
        $textmateFormatter->update($this->mock('SplSubject'), $reportEvent);
        
        // $textmateLink = $this->_buildLink('./_files/spec/FooSpec.php:8');
        // $this->property($textmateFormatter, '_examples')
        //     ->should
        
    }
    
    private function _buildLink($backtraceLine)
    {
        list ($path, $line) = explode(':', $backtraceLine);
        $url = sprintf(self::TEXTMATE_URL, realpath($path), $line);
        return sprintf("<a href=\"%s\">%s</a>", $url, $backtraceLine);
    }
    
    private function _buildReporter()
    {
        $failure = $this->mock("\SplObjectStorage");
        $failure->shouldReceive('count')
                ->andReturn(1);
        $otherResults = $this->mock("\SplObjectStorage");
        $otherResults->shouldReceive('count')
                ->andReturn(0);
        
        $reporter = $this->mock('PHPSpec\Runner\Reporter');
        $reporter->shouldReceive('hasMessage')
                 ->andReturn(false);
        $reporter->shouldReceive('getFailures')
                 ->andReturn($failure);
        $reporter->shouldReceive('getErrors')
                 ->andReturn($otherResults);
        $reporter->shouldReceive('getPendingExamples')
                 ->andReturn($otherResults);
        $reporter->shouldReceive('getExceptions')
                 ->andReturn($otherResults);
        $reporter->shouldReceive('getPassing')
                 ->andReturn($otherResults);
        $reporter->shouldReceive('getRuntime')
                 ->andReturn(1);
                 
        return $reporter;
    }
    
    private function _buildReportEvent()
    {
        $reportEvent = $this->mock('SplSubject');
        $reportEvent->event = 'status';
        $reportEvent->status = 'F';
        $reportEvent->example = 'itShouldDoStuff';
        $reportEvent->message = 'it didn\'t do it properly';
        $reportEvent->exception = $this->mock(new \PHPSpec\Exception);
        return $reportEvent;
    }
    
}