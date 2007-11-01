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

/** PHPSpec_Framework */
require_once 'PHPSpec/Framework.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007 Pádraic Brady, Travis Swicegood
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec_Console_Command
{

    public static function main()
    {
        $runnable = array();
        $options = new PHPSpec_Console_Getopt;

        // check for straight class to execute
        if (isset($options->specFile)) {
            $loader = new PHPSpec_Runner_Loader_Classname;
            $runnable += $loader->load($options->specFile);
        }

        if (isset($options->r)) {
            $loader = new PHPSpec_Runner_Loader_DirectoryRecursive;
            $runnable += $loader->load( getcwd() );
        }

        if (isset($options->specdox) || isset($options->s)) {
            $generateSpecdox = true;
        }

        if (empty($runnable)) {
            echo 'No specs to execute!';
            return;
        }

        $result = new PHPSpec_Runner_Result;
        foreach ($runnable as $behaviourContextReflection) {
            $contextObject = $behaviourContextReflection->newInstance();
            $collection = new PHPSpec_Runner_Collection($contextObject);
            $runner = PHPSpec_Runner_Base::execute($collection, $result);
        }

        // use a Text reporter for console output
        $textReporter = new PHPSpec_Runner_Reporter_Text( $runner->getResult() );
        if ($generateSpecdox) {
            $textReporter->doSpecdox();
        }
        echo $textReporter;

    }

}

PHPSpec_Console_Command::main();