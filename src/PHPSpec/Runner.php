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
namespace PHPSpec;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Runner
{

    /**
     * Runs the examples with the selected options
     * 
     * @param array $options
     */
    public function run($options)
    {
        $runnable = array();
        $generateSpecdox = false;
                                    
        // check for straight class to execute
        if (isset($options->specFile)) {
            $options->specFile = rtrim($options->specFile, DIRECTORY_SEPARATOR);
            $pathToFile = getcwd();
            if (false !== strpos($options->specFile, '/')) {
                $pathToFile = str_replace(
                    "/" . basename($options->specFile), '', $options->specFile
                );
                $options->specFile = basename($options->specFile);
            }
            if (is_dir(realpath($pathToFile . "/$options->specFile"))) {
                $loader = new Runner\Loader\DirectoryRecursive;
                $runnable += $loader->load(
                    realpath($pathToFile . "/$options->specFile")
                );
            } else {
                $loader = new Runner\Loader\Classname;
                $runnable += $loader->load($options->specFile, $pathToFile);
            }
        }
    
        // should only recurse if not running a single spec
        if ((isset($options->r) ||
            isset($options->recursive)) &&
            !isset($options->specFile)) {
            $loader = new Runner\Loader\DirectoryRecursive;
            $runnable += $loader->load(getcwd());
        }

        if (isset($options->s) || isset($options->specdoc)) {
            $generateSpecdox = true;
        }

        if (empty($runnable)) {
            echo 'No specs to execute!' . PHP_EOL;
            return;
        }

        $result = new Runner\Result;
        
        $reporter = \PHPSpec\Runner\Reporter::create(
            $result, $options->reporter
        );

        if ($options->c || $options->color || $options->colour) {
            $reporter->showColors(true);
        }
        
        $result->setReporter($reporter); 
        
        $result->setRuntimeStart(microtime(true));
        
        foreach ($runnable as $behaviourContextReflection) {
            $contextObject = $behaviourContextReflection->newInstance();
            $collection = new Runner\Collection($contextObject);
            $runner = Runner\Base::execute($collection, $result);
        }
        
        $result->setRuntimeEnd(microtime(true));

        $reporter->output($generateSpecdox);
    }
}
