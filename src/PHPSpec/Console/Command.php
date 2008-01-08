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

/** PHPSpec_Framework */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Framework.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Console_Command
{

    /**
     * 
     * @todo should not directly echo reporter since some will pass mesgs only
     * @param PHPSpec_Console_Getopt $options
     */
    public static function main(PHPSpec_Console_Getopt $options = null)
    {
        if (is_null($options)) {
        	$options = new PHPSpec_Console_Getopt;
        }
        if (isset($options->a) || isset($options->autotest)) {
        	self::autotest($options);
        	return;
        }
        if (!isset($options->reporter)) {
        	$options->reporter = 'Console';
        } 
        PHPSpec_Runner::run($options);
    }

    /**
     * The autotest() static method serves as PHPSpec's Autotester. It will
     * run all tests continually, with 10 second delays between each
     * iterative run and report as normal for each iteration to the console
     * output.
     * 
     * Use the CTRL+C key combination to trigger an exit from the console
     * running loop used for Autotesting.
     *
     * @param PHPSpec_Console_Getopt $options
     */
    public static function autotest(PHPSpec_Console_Getopt $options)
    {
        set_time_limit(0);
        
    	if (isset($options->a)) {
    		$options->a = null;
    	}
        if (isset($options->autotest)) {
            $options->autotest = null;
        }

    	while(true) {
    	    self::main($options);
    	    sleep(10);
    	}
    }
    
}

PHPSpec_Console_Command::main();