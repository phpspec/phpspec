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
        $str .= ', ' . $this->_result->countPasses() . ' passed';
        
        if ($this->_result->countFailures() > 0) {
        	$str .= ', ' . $this->_result->countFailures() . ' failed';
        }
        if ($this->_result->countErrors() > 0) {
            $str .= ', ' . $this->_result->countErrors() . ' errors';
        }
        if ($this->_result->countExceptions() > 0) {
            $str .= ', ' . $this->_result->countExceptions() . ' exceptions';
        }
        if ($this->_result->countPending() > 0) {
            $str .= ', ' . $this->_result->countPending() . ' pending';
        }
        
        if ($this->_result->countFailures() > 0) {
            $failed = $this->_result->getTypes('failure');
            foreach ($failed as $failure) {
                $str .= $failure->getContextDescription();
                $str .= ' => ' . $failure->getSpecificationText();
                $str .= ' => ' . $failure->getFailedMessage();
                $str .= PHP_EOL;
            }
        }
        if ($this->_result->countErrors() > 0) {
            $errors = $this->_result->getTypes('error');
            foreach ($errors as $error) {
                $str .= $error->getContextDescription();
                $str .= ' => ' . $error->getSpecificationText();
                $str .= ' => ' . $error->getMessage();
                $str .= PHP_EOL;
            }
        }
        if ($this->_result->countExceptions() > 0) {
            $exceptions = $this->_result->getTypes('exception');
            foreach ($exceptions as $exception) {
                $str .= $exception->getContextDescription();
                $str .= ' => ' . $exception->getSpecificationText();
                $str .= ' => ' . $exception->getMessage();
                $str .= PHP_EOL;
            }
        }
        if ($this->_result->countPending() > 0) {
            $pendings = $this->_result->getTypes('pending');
            foreach ($pendings as $pending) {
                $str .= $pending->getContextDescription();
                $str .= ' => ' . $pending->getSpecificationText();
                $str .= ' => ' . $pending->getMessage();
                $str .= PHP_EOL;
            }
        }
        
        return $str . PHP_EOL;
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