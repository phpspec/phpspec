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

/**
 * Wrapper for {@link \PHPSpec\Specification::getSpec()}
 * 
 * @return \PHPSpec\Specification
 */
function describe()
{
    $args = func_get_args();
    return call_user_func_array(
        array("\\PHPSpec\\Specification",'getSpec'), $args
    );
}

/**
 * Converts errors in ErrorException so the runner can display them nicely
 * 
 * @param integer $errno
 * @param string  $errstr
 * @param string  $errfile
 * @param integer $errline
 * @throws \PHPSpec\Runner\ErrorException
 * @return null|boolean
 */
function PHPSpec_ErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!($errno & error_reporting())) {
        return;
    }

    $backtrace = debug_backtrace();
    array_shift($backtrace);

    include_once 'PHPSpec/Runner/ErrorException.php';
    $e = new \PHPSpec\Runner\ErrorException($errstr, $errno);
    $e->setFile($errfile);
    $e->setLine($errline);
    $e->setTrace($backtrace);
    throw $e;

    return true;
}