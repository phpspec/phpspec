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
 * @copyright  Copyright (c) 2007 P�draic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 P�draic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Runner_Reporter_Text extends PHPSpec_Runner_Reporter
{

    public function output()
    {
    	echo $this;
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
    
    public function toString()
    {
        $str = PHP_EOL . PHP_EOL;
        $str .= 'Finished in ' . $this->_result->getRuntime() . ' seconds';
        $str .= PHP_EOL . PHP_EOL . count($this->_result) . ' examples';
        
        $count = $this->_result->countFailures();
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
        if ($this->_result->countFailures() > 0) {
            $failed = $this->_result->getTypes('fail');
            foreach ($failed as $failure) {
                $reportedIssues .= $failure->getContextDescription();
                $reportedIssues .= ' => ' . $failure->getSpecificationText();
                $reportedIssues .= ' => ' . $failure->getFailedMessage();
                $reportedIssues .= PHP_EOL;
            }
        }
        if ($this->_result->countErrors() > 0) {
            $errors = $this->_result->getTypes('error');
            foreach ($errors as $error) {
                $reportedIssues .= $error->getContextDescription();
                $reportedIssues .= ' => ' . $error->getSpecificationText();
                $reportedIssues .= ' => ' . $error->getMessage();
                $reportedIssues .= PHP_EOL;
            }
        }
        if ($this->_result->countExceptions() > 0) {
            $exceptions = $this->_result->getTypes('exception');
            foreach ($exceptions as $exception) {
                $reportedIssues .= $exception->getContextDescription();
                $reportedIssues .= ' => ' . $exception->getSpecificationText();
                $reportedIssues .= ' => ' . $exception->getMessage();
                $reportedIssues .= PHP_EOL;
            }
        }
        if ($this->_result->countPending() > 0) {
            $pendings = $this->_result->getTypes('pending');
            foreach ($pendings as $pending) {
                $reportedIssues .= $pending->getContextDescription();
                $reportedIssues .= ' => ' . $pending->getSpecificationText();
                $reportedIssues .= ' => ' . $pending->getMessage();
                $reportedIssues .= PHP_EOL;
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
        return 'specdox';
    }

}