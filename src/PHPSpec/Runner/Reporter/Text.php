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
namespace PHPSpec\Runner\Reporter;

/**
 * @see \PHPSpec\Runner\Reporter
 */
use PHPSpec\Runner\Reporter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
abstract class Text extends Reporter
{

    /**
     * Echoes the output of {@ling self::toString()}
     * 
     * @param boolean $specs
     */
    public function output($specs = false)
    {
        echo $this->toString($specs);
    }
    
    /**
     * Output a status symbol after each test run.
     * . for Pass, E for error/exception, F for failure, and P for pending
     *
     * @param string $symbol
     */
    public function outputStatus($symbol)
    {
        echo $symbol;
    }
    
    /**
     * Returns the result of all specs
     * @see PHPSpec\Runner.Reporter::toString()
     * 
     * @param boolean $specs
     * 
     * @return string
     */
    public function toString($specs = false)
    {
        $str = 'Finished in ' . $this->_result->getRuntime() . ' seconds';

        if ($specs) {
            $str .= PHP_EOL . PHP_EOL . $this->getSpecDox();
        }

        $str .= $this->getTotals();
        
        $reportedIssues = PHP_EOL . PHP_EOL;
        
        $increment = 1;
        if ($this->hasPending()) {
            $reportedIssues .= 'Pending:' . PHP_EOL;
            $pendings = $this->_result->getTypes('pending');
            foreach ($pendings as $pending) {
                $reportedIssues .= $this->formatReportedIssue(
                    $increment, $pending, $pending->getMessage(), 'PENDING'
                );
                $reportedIssues .= $this->formatLines(
                    $this->getPrettyTrace($pending->getException(), 1)
                );
            }
            $reportedIssues .= PHP_EOL;
        }
        
        if ($this->_result->countFailures() > 0 ||
            $this->_result->countDeliberateFailures() > 0) {
            $reportedIssues .= 'Failures:' . PHP_EOL . PHP_EOL;
            $failed = $this->_result->getTypes('fail');
            $increment = 1;
            foreach ($failed as $failure) {
                $reportedIssues .= $this->formatReportedIssue(
                    $increment, $failure, $failure->getFailedMessage()
                );
                $reportedIssues .= $this->formatLines(
                    '     # ' . $failure->getLine()
                ) . PHP_EOL . PHP_EOL;
            }
            if ($this->_result->countDeliberateFailures() > 0) {
                $failed = $this->_result->getTypes('deliberateFail');
                foreach ($failed as $failure) {
                    $reportedIssues .= $this->formatReportedIssue(
                        $increment, $failure, $failure->getMessage()
                    );
                }
            }
        }
        
        $increment = 1;
        if ($this->_result->countErrors() > 0) {
            $reportedIssues .= 'Errors:' . PHP_EOL . PHP_EOL;
            $errors = $this->_result->getTypes('error');
            foreach ($errors as $error) { 
                $reportedIssues .= $this->formatReportedIssue(
                    $increment,
                    $error,
                    $error->getException()->getErrorType() .
                    ': ' . $error->toString(), 'ERROR'
                );
                if (method_exists($error, 'getPrettyTrace')) {
                     $reportedIssues .= $this->formatLines(
                         $error->getPrettyTrace(3)
                     ). PHP_EOL;
                }
            }
        }

        $increment = 1;
        if ($this->_result->countExceptions() > 0) {
            $reportedIssues .= 'Exceptions:' . PHP_EOL . PHP_EOL;
            $exceptions = $this->_result->getTypes('exception');
            foreach ($exceptions as $exception) {
                if ($exception instanceof \PHPSpec\Runner\Example\Pending ||
                    $exception instanceof \PHPSpec\Runner\Example\Fail ||
                    $exception instanceof \PHPSpec\Runner\Example\Error ||
                    $exception instanceof 
                    \PHPSpec\Runner\Example\DeliberateFail) {
                    continue;
                }
                $reportedIssues .= $this->formatReportedIssue(
                    $increment, $exception, $exception->toString(), 'EXCEPTION'
                );
                $reportedIssues .= $this->formatLines(
                    $this->getPrettyTrace($exception->getException(), 3) .
                    PHP_EOL
                );
            }
        }
        
        return $reportedIssues . $str . PHP_EOL;
    }

    /**
     * Checks whether there are any pending specs
     * 
     * @return boolean
     */
    public function hasPending()
    {
        return  $this->_result->countPending() > 0;
    }

    /**
     * Formats the reported issues line
     * 
     * @param \PHPSpec\Runner\Example\Exception $issue
     * @param string                            $message
     * @param string                            $issueType
     * @return string
     */
    public function formatReportedIssue($issue, $message, $issueType = 'FAILED')
    {
        $reportedIssues = '     ';
        if ($issueType === 'PENDING') {
            $reportedIssues .= '# ';
        }
        
        if ($issueType === 'FAILED') {
            $reportedIssues .= $this->formatFailedMessage($issue);
        }
        
        $reportedIssues .= $message;
        $reportedIssues .= PHP_EOL . ($issueType !== 'FAILED' &&
                                      $issueType !== 'ERROR' &&
                                      $issueType !== 'PENDING' &&
                                      $issueType !== 'EXCEPTION' ?
                                      PHP_EOL : '');
        return $reportedIssues;
    }
    
    public function formatFailedMessage($issue)
    {
        $failedMessage = 'Failure/Error: ';
        list($path, $line) = explode(':', $issue->getLine());
        $source = file($path);
        $lineSource = $source[$line-1];
        $failedMessage .= trim($lineSource);
        $failedMessage .= PHP_EOL . '     ';
        
        return $failedMessage;
    }

    /**
     * Returns the totals for each of the issue types 
     *
     * @return string
     */
    public function getTotals()
    {
        $str = PHP_EOL . count($this->_result) . ' examples';
        
        $count = $this->_result->countFailures() +
                 $this->_result->countDeliberateFailures();
        if ($count == 1) {
            $str .= ', ' . $count . ' failure';
        } else {
            $str .= ', ' . $count . ' failures';
        }
            
        if ($this->_result->countErrors() > 0) {
            $count = $this->_result->countErrors();
            if ($count == 1) {
                $str .= ', ' . $count . ' error';
            } else {
                $str .= ', ' . $count . ' errors';
            }
        }
        if ($this->_result->countExceptions() > 0) {
            $count = $this->_result->countExceptions();
            if ($count == 1) {
                $str .= ', ' . $count . ' exception';
            } else {
                $str .= ', ' . $count . ' exceptions';
            }
        }
        if ($this->_result->countPending() > 0) {
            $str .= ', ' . $this->_result->countPending() . ' pending';
        }
        return $str;
    }

    /**
     * Checks whether there are any issues
     * 
     * @return boolean
     */
    public function hasIssues()
    {
          return ($this->_result->countFailures() +
                  $this->_result->countDeliberateFailures() +
                  $this->_result->countErrors() +
                  $this->_result->countExceptions()) > 0;
          
    }

    /**
     * Returns the result of all specs. Alias of {@link self::toString()}
     * 
     * @see PHPSpec\Runner.Reporter::__toString()
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Gets the descriptions of all examples
     * 
     * @see PHPSpec\Runner.Reporter::getSpecdox()
     */
    public function getSpecdox()
    {
        $examples = $this->_result->getExamples();
        $contexts = array();
        $str = '';
        foreach ($examples as $example) {
            if (!isset($contexts[$example->getContextDescription()])) {
                $contexts[$example->getContextDescription()] = array();
            }
            $contexts[$example->getContextDescription()][] = $example;
        }
        foreach ($contexts as $description => $arrayOfExamples) {
           $str .=  $this->_format($description) . PHP_EOL;
           foreach ($arrayOfExamples as $example) {
               $str .= '  -' . $example->getSpecificationText();
               if (!$example instanceof \PHPSpec\Runner\Example\Pass) {
                    $class = get_class($example);
                    $parts = explode('_', $class);
                    $type = array_pop($parts);
                    $str .= ' (' . strtoupper($type) . ')';
               }
               $str .= PHP_EOL;
           }
           $str .= PHP_EOL . PHP_EOL;
        }
        return $str;
    }

    /**
     * Removes spec from the end or describe from the begining of a spec
     * 
     * @param string $description
     * @return string
     */
    protected function _format($description)
    {
        $description = preg_replace(
            '/spec$/', '', preg_replace('/^describe ?/', '', $description)
        );
        return str_replace(' ', '', ucwords($description)) . ' ';
    }
    
    /**
     * Formats the lines
     * 
     * @param string $lines
     * @return string
     */
    abstract public function formatLines($lines);

}