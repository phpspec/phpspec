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

require_once 'Console/Color.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Runner_Reporter_Console extends PHPSpec_Runner_Reporter_Text
{
    /**
     * Output a status symbol after each test run.
     * . for Pass, E for error/exception, F for failure, and P for
     * pending.
     * This subclass of the Text reporter attempts to use console text
     * coloring where available.
     *
     * @param string $symbol
     */
    public function outputStatus($symbol)
    {
        // Windows NT (or earlier without ANSI.SYS) do not
        // support the ANSI color escape codes.
        if (preg_match('/windows/i', php_uname('s'))) {
            echo $symbol;
            return;
        }
        switch ($symbol) {
            case '.':
                $symbol = $this->_showColors ? Console_Color::convert("%g$symbol%n") : $symbol;
                break;
            case 'F':
            case 'E':
                $symbol = $this->_showColors ? Console_Color::convert("%r$symbol%n") : $symbol;
                break;
            case 'P':
                $symbol = $this->_showColors ? Console_Color::convert("%y$symbol%n") : $symbol;
                break;
            default:
        }
    	echo $symbol;
    }
    
    public function getTotals()
    {
		$totals = parent::getTotals();
		if ($this->_showColors) {
			return $this->hasIssues() ?
	            Console_Color::convert("%r" . $totals . "%n") :
	            Console_Color::convert("%g" . $totals . "%n");
		}
	    return $totals;
	}
	
	public function formatReportedIssue(&$increment, $issue, $message, $issueType = 'FAILED')
    {
		$issues = parent::formatReportedIssue(&$increment, $issue, $message, $issueType);
		if ($this->_showColors) {
			return $this->hasIssues() ?
				Console_Color::convert("%r" . $issues . "%n") :
				Console_Color::convert("%g" . $issues . "%n");
		}
		return $issues;
	}
}