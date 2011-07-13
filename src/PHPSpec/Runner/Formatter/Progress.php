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
namespace PHPSpec\Runner\Formatter;

use \PHPSpec\Runner\Formatter,
    \PHPSpec\Runner\Reporter;

class Progress implements Formatter
{
    protected $_reporter;
    protected $_showColors = false;
    protected $_enableBacktrace = false;
    
    public function __construct(Reporter $reporter)
    {
        $this->_reporter = $reporter;
    }
    
    public function output()
    {
        if ($this->justShowAMessage()) {
            return;
        }
        
        $this->printLines(2);
        
        $this->printPending();
        $this->printFailures();
        $this->printErrors();
        $this->printExceptions();
        
        $this->printRuntime();
        $this->printTotals();
    }
    
    public function setShowColors($showColors)
    {
        $this->_showColors = $showColors;
    }
    
    public function setEnableBacktrace($enableBacktrace)
    {
        $this->_enableBacktrace = $enableBacktrace;
    }
    
    public function showColors()
    {
        return $this->_showColors;
    }
    
    protected function printLines($lines)
    {
        $this->put(str_repeat(PHP_EOL, $lines));
    }
    
    protected function printPending()
    {
        if ($this->_reporter->hasPendingExamples()) {
            $this->printIncrementalResult(
                'Pending', $this->_reporter->getPendingExamples(), 1
            );
        }
    }
    
    protected function printFailures()
    {
        if ($this->_reporter->hasFailures()) {
            $this->printIncrementalResult(
                'Failures', $this->_reporter->getFailures()
            );
        }
    }
    
    protected function printErrors()
    {
        if ($this->_reporter->hasErrors()) {
            $this->printIncrementalResult(
                'Errors', $this->_reporter->getErrors()
            );
        }
    }
    
    protected function printExceptions()
    {
        if ($this->_reporter->hasExceptions()) {
            $this->printIncrementalResult(
                'Exceptions', $this->_reporter->getExceptions()
            );
        }
    }
    
    protected function printIncrementalResult($type, $items, $space = 2)
    {
        $this->put("$type:");
        $this->printLines($space);
        $increment = 1;
        $items->rewind();
        while ($items->valid()) {
            $item = $items->current();
            $example = $items->getInfo();
            $method = "getMessageFor$type";
            $message = $this->$method($increment, $item, $example, $this->_enableBacktrace);
            $this->put($message);
            $this->printLines(1);
            $items->next();
            $increment++;
        } 
    }
    
    protected function getMessageForFailures($increment, $failure, $example, $backtrace)
    {
        $snippet = 1;
        if ($failure instanceof \PHPSpec\Specification\Result\DeliberateFailure) {
            $snippet = 0;
        }
         $trace = $backtrace ? null : 3;
        return <<<MESSAGE
  $increment) {$example->getDescription()}
     {$this->red('Failure\Error: ' . $failure->getSnippet($snippet))}
     {$this->red($failure->getMessage())}
{$this->grey($failure->prettyTrace($trace))}
MESSAGE;
    }
    
    protected function getMessageForErrors($increment, $error, $example, $backtrace)
    {
        $trace = $backtrace ? null : 3;
        return <<<MESSAGE
  $increment) {$example->getDescription()}
     {$this->red(get_class($error) . ': ' . $error->getSnippet(1))}
     {$this->red($error->getErrorType() . ': ' . $error->getMessage())}
{$this->grey($error->prettyTrace($trace))}
MESSAGE;
    }
    
    protected function getMessageForExceptions($increment, $exception, $example, $backtrace)
    {
        $trace = $backtrace ? null : 3;
        return <<<MESSAGE
  $increment) {$example->getDescription()}
     {$this->red( 'Failure\Exception: ' . $exception->getSnippet(1))}
     {$this->red($exception->getExceptionClass() . ': ' . $exception->getMessage())}
{$this->grey($exception->prettyTrace($trace))}
MESSAGE;
    }
    
    protected function getMessageForPending($increment, $pending, $example, $backtrace)
    {
        return <<<MESSAGE
  {$this->yellow($example->getDescription())}
     {$this->grey('# ' . $pending->getMessage())}
{$this->grey($pending->prettyTrace(1))}
MESSAGE;
    }
    
    protected function justShowAMessage()
    {
        if ($this->_reporter->hasMessage()) {
            $this->put($this->_reporter->getMessage());
            return true;
        }
        return false;
    }
    
    protected function printRuntime()
    {
        $this->put("Finished in " . $this->_reporter->getRuntime() . " seconds");
        $this->printLines(1);
    }
    
    protected function printTotals()
    {
        $this->put($this->getTotals());
        $this->printLines(1);
    }
    
    protected function getTotals()
    {
        $failures = $this->_reporter->getFailures()->count();
        $errors = $this->_reporter->getErrors()->count();
        $pending = $this->_reporter->getPendingExamples()->count();
        $exceptions = $this->_reporter->getExceptions()->count();
        $passing = count($this->_reporter->getPassing());
        
        $total = $failures + $errors + $pending + $exceptions + $passing;
        
        $totals = "$total example" . ($total !== 1 ? "s" : "");
        if ($failures) {
            $plural = $failures !== 1 ? "s" : "";
            $totals .= ", $failures failure$plural";
        }
        if ($errors) {
            $plural = $errors !== 1 ? "s" : "";
            $totals .= ", $errors error$plural";
        }
        if ($exceptions) {
            $plural = $exceptions !== 1 ? "s" : "";
            $totals .= ", $exceptions exception$plural";
        }
        if ($pending) {
            $plural = $pending !== 1 ? "s" : "";
            $totals .= ", $pending pending$plural";
        }
        if ($failures || $errors || $exceptions) {
            $totals = $this->red($totals);
        } elseif ($pending) {
            $totals = $this->yellow($totals);
        } elseif ($passing) {
            $totals = $this->green($totals);
        }
        return $totals;
    }
    
    public function update(\SplSubject $method)
    {
        $args = func_get_args();
        
        switch($args[1][0]) {
            case 'status':
                $this->status($args[1][1]);
                break;
            case 'exit':
                $this->output();
                exit;
                break;
        }
    }
    
    protected function status($status)
    {
        switch($status) {
            case '.':
                $this->put($this->green($status));
                break;
            case '*':
                $this->put($this->yellow($status));
                break;
            case 'E':
            case 'F':
                $this->put($this->red($status));
                break;
        }
    }
    
    public function green($output)
    {
        if ($this->showColors()) {
            $output = Color::green($output);
        }
        return $output;
    }
    
    public function red($output)
    {
        if ($this->showColors()) {
            $output = Color::red($output);
        }
        return $output;
    }
    
    public function grey($output)
    {
        if ($this->showColors()) {
            $output = Color::grey($output);
        }
        return $output;
    }
    
    public function yellow($output)
    {
        if ($this->showColors()) {
            $output = Color::yellow($output);
        }
        return $output;
    }
    
    public function put($output)
    {
        Stdout::put($output);
    }
}