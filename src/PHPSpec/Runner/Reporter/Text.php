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
 * to license@phpspec.org so we can send you a copy immediately.
 *
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Runner_Reporter_Text extends PHPSpec_Runner_Reporter
{

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
    
    public function toString($specs = false)
    {
        $str = PHP_EOL . PHP_EOL;
        $str .= 'Finished in ' . $this->_result->getRuntime() . ' seconds';

        if ($specs) {
            $str .= PHP_EOL . PHP_EOL . $this->getSpecDox();
        }

        $str .= PHP_EOL . PHP_EOL . count($this->_result) . ' examples';
        
        $count = $this->_result->countFailures() + $this->_result->countDeliberateFailures();
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
        
        $reportedIssues = PHP_EOL . PHP_EOL;
        if ($this->_result->countFailures() > 0 || $this->_result->countDeliberateFailures() > 0) {
            $reportedIssues .= 'Failures:' . PHP_EOL . PHP_EOL;
            $failed = $this->_result->getTypes('fail');
            $increment = 1;
            foreach ($failed as $failure) {
                $reportedIssues .= $increment . ')' . PHP_EOL;
                $reportedIssues .= '\'' . $this->_format($failure->getContextDescription());
                $reportedIssues .= $failure->getSpecificationText() . '\' FAILED';
                $reportedIssues .= PHP_EOL . $failure->getFailedMessage();
                $reportedIssues .= PHP_EOL . PHP_EOL;
                $increment++;
            }
            if ($this->_result->countDeliberateFailures() > 0) {
                $failed = $this->_result->getTypes('deliberateFail');
                foreach ($failed as $failure) {
                    $reportedIssues .= $increment . ')' . PHP_EOL;
                    $reportedIssues .= '\'' . $this->_format($failure->getContextDescription());
                    $reportedIssues .= $failure->getSpecificationText() . '\' FAILED';
                    $reportedIssues .= PHP_EOL . $failure->getMessage();
                    $reportedIssues .= PHP_EOL . PHP_EOL;
                    $increment++;
                }
            }
        }
        
        $increment = 1;
        if ($this->_result->countErrors() > 0) {
            $reportedIssues .= 'Errors:' . PHP_EOL . PHP_EOL;
            $errors = $this->_result->getTypes('error');
            foreach ($errors as $error) {
                $reportedIssues .= $increment . ')' . PHP_EOL;
                $reportedIssues .= '\'' . $this->_format($error->getContextDescription());
                $reportedIssues .= $error->getSpecificationText() . '\' ERROR';
                $reportedIssues .= PHP_EOL . $error->toString();
                $reportedIssues .= PHP_EOL . PHP_EOL;
                $increment++;
            }
        }

        $increment = 1;
        if ($this->_result->countExceptions() > 0) {
            $reportedIssues .= 'Exceptions:' . PHP_EOL . PHP_EOL;
            $exceptions = $this->_result->getTypes('exception');
            foreach ($exceptions as $exception) {
                $reportedIssues .= $increment . ')' . PHP_EOL;
                $reportedIssues .= '\'' . $this->_format($exception->getContextDescription());
                $reportedIssues .= $exception->getSpecificationText() . '\' EXCEPTION';
                $reportedIssues .= PHP_EOL . $exception->toString();
                $reportedIssues .= PHP_EOL . PHP_EOL;
                $increment++;
            }
        }

        $increment = 1;
        if ($this->_result->countPending() > 0) {
            $reportedIssues .= 'Pending:' . PHP_EOL . PHP_EOL;
            $pendings = $this->_result->getTypes('pending');
            foreach ($pendings as $pending) {
                $reportedIssues .= $increment . ')' . PHP_EOL;
                $reportedIssues .= '\'' . $this->_format($pending->getContextDescription());
                $reportedIssues .= $pending->getSpecificationText() . '\' PENDING';
                $reportedIssues .= PHP_EOL . $pending->getMessage();
                $reportedIssues .= PHP_EOL . PHP_EOL;
                $increment++;
            }
        }
        
        return $str . $reportedIssues . PHP_EOL;
    }

    public function __toString()
    {
        return $this->toString();
    }

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
        foreach ($contexts as $description=>$arrayOfExamples) {
           $str .=  $this->_format($description) . PHP_EOL;
           foreach($arrayOfExamples as $example) {
               $str .= '  -' . $example->getSpecificationText();
               if (!$example instanceof PHPSpec_Runner_Example_Pass) {
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

    protected function _format($description)
    {
        $description = preg_replace('/spec$/', '', preg_replace('/^describe ?/', '', $description));
        return $description . ' ';
    }

}