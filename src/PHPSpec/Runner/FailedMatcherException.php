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
class PHPSpec_Runner_FailedMatcherException extends PHPSpec_Exception
{
    protected $formattedLine;
    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
         parent::__construct($message, $code, $previous);
         $this->formattedLine = $this->formatFailureLine();
    }
    
    public function getFormattedLine()
    {
        return $this->formattedLine;
    }
    
    public function setFormattedLine($formattedLine)
    {
        return $this->formattedLine = $formattedLine;
    }
    
    protected function formatFailureLine()
    {
        $trace = $this->getTrace();
        while($step = next($trace)) {
            if (strpos($step['class'], 'Describe') === 0 ||
                strpos($step['class'], 'Spec') === strlen($step['class']) - 4) {
                $failure = prev($trace);
                return $failure['file'] . ":" . $failure['line'];
            }
        }
    }
}