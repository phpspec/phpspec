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
namespace PHPSpec\Runner;

/**
 * @see \PHPSpec\Exception
 */
use \PHPSpec\Exception;

/**
 * @see \PHPSpec\Util\Backtrace
 */
use PHPSpec\Util\Backtrace;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class FailedMatcherException extends Exception
{
    /**
     * The exception failure path to file and line like:
     * Path/To/Exception:42
     * Where 42 is the line where the failure happened
     * 
     * @var string
     */
    protected $_formattedLine;
    
    /**
     * Failed Matcher Exception is constructed with message, code and exception
     * 
     * @param string $message
     * @param integer $code
     * @param \Exception $previous
     */
    public function __construct($message = null, $code = 0,
                                \Exception $previous = null)
    {
         parent::__construct($message, $code, $previous);
         $this->_formattedLine = $this->formatFailureLine();
    }
    
    /**
     * Gets the formattted line
     * 
     * @return string
     */
    public function getFormattedLine()
    {
        return $this->_formattedLine;
    }
    
    /**
     * Sets the formatted line
     * 
     * @param string $formattedLine
     */
    public function setFormattedLine($formattedLine)
    {
        $this->_formattedLine = $formattedLine;
    }
    
    /**
     * Formats and returns the failure line to format:
     * Path/To/Exception:42
     * 
     * @return string
     */
    protected function formatFailureLine()
    {
        $trace = $this->getTrace();
        $step = null;
        
        while (($step = next($trace)) !== null) {
            if (isset($step['class']) &&
                (strpos($step['class'], 'Describe') === 0 ||
                strpos($step['class'], 'Spec') ===
                strlen($step['class']) - 4)) {
                $failure = prev($trace);
                $pathToFile = Backtrace::shortenRelativePath($failure['file']);
                return $pathToFile . ":" . $failure['line'];
            }
        }
    }
}
