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
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec\Extensions;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Autotest
{
    /**
     * @FIXME! Replace this with Autorun.php
     * 
     * The autotest method serves as PHPSpec's Autotester. It will
     * run all tests continually, with 10 second delays between each
     * iterative run and report as normal for each iteration to the console
     * output.
     * 
     * Use the CTRL+C key combination to trigger an exit from the console
     * running loop used for Autotesting.
     *
     * @param \PHPSpec\Console\Command $options
     */   
    public function run(\PHPSpec\Console\Command $command)
    {
        set_time_limit(0);
        
        if (isset($command->getOptions()->a)) {
            $command->getOptions()->a = null;
        }
        if (isset($command->getOptions()->autotest)) {
            $command->getOptions()->autotest = null;
        }

        while (true) {
            $command->run($command->getOptions());
            sleep(10);
        }
    }
}