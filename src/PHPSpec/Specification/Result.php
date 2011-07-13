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
namespace PHPSpec\Specification;

use \PHPSpec\Exception,
    \PHPSpec\Util\Backtrace;

abstract class Result extends Exception
{
    public function getSnippet($index = 0)
    {
        return Backtrace::code($this->getTrace(), $index);
    }
    
    public function prettyTrace($limit = 3)
    {
        return Backtrace::pretty($this->getTrace(), $limit);
    }
    
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!($errno & error_reporting())) {
            return;
        }

        $backtrace = debug_backtrace();
        array_shift($backtrace);

        throw new Result\Error(
            $errstr, $errno, $errfile, $errline, $backtrace
        );

        return true;
    }
}