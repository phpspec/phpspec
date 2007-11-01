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
class PHPSpec_Framework
{
    public function __construct()
    {

    }

    public static function autoload($class)
    {
        // @todo consider speed implications
        if (substr($class, 0, 8) != 'PHPSpec_') {
            return false;
        }
        $path = dirname(dirname(__FILE__));
        include_once $path . '/' . str_replace('_', '/', $class) . '.php';
    }

}

spl_autoload_register(array(
    'PHPSpec_Framework',
    'autoload'
));

function describe()
{
    $args = func_get_args();
    return call_user_func_array(array('PHPSpec_Specification','getSpec'), $args);
}

function PHPSpec_ErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!($errno & error_reporting())) {
        return;
    }

    $backtrace = debug_backtrace();
    array_shift($backtrace);

    throw new PHPSpec_Runner_ErrorException($errstr, $errno, $errfile, $errline, $backtrace);

    return true;
}