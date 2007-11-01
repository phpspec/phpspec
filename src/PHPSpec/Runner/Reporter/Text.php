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

    /**
     * really need to dump this crap and put in a proper output block
     */

    public function toString()
    {
        $str = '';
        $str .= PHP_EOL . count($this->_result) . ' Specs Executed:' . PHP_EOL;
        $str .= count($this->_result->getPasses()) . ' Specs Passed' . PHP_EOL;
        
        $failed = $this->_result->getFailures();

        if (count($failed) > 0) {
            foreach ($failed as $failure) {
                $str .= $failure->getContextDescription();
                $str .= ' => ' . $failure->getSpecificationText();
                $str .= ' => ' . $failure->getFailedMessage();
                $str .= PHP_EOL;
            }
        }

        $exceptions = $this->_result->getExceptions();
        $errors = $this->_result->getErrors(); 

        if (count($exceptions) > 0) {
            foreach ($exceptions as $exception) {
                $str .= $exception->getContextDescription();
                $str .= ' => ' . $exception->getSpecificationText();
                $str .= ' => ' . $exception->getMessage();
                $str .= PHP_EOL;
            }
        }
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $str .= $error->getContextDescription();
                $str .= ' => ' . $error->getSpecificationText();
                $str .= ' => ' . $error->getMessage();
                $str .= PHP_EOL;
            }
        }

        $str .= 'DONE' . PHP_EOL;
        return $str;
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