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
 * @see \PHPSpec\Exception.php
 */
require_once 'PHPSpec/Exception.php';

require_once 'PHPSpec/Describe/Functions.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Framework
{
    const VERSION = '1.0.1beta';
    
    /**
     * Framework loader
     * 
     * @param string $class
     * @throws \PHPSpec\Exception
     * @return boolean
     */
    public static function autoload($class)
    {
        // @todo consider speed implications
        if (substr($class, 0, 7) != 'PHPSpec') {
            return false;
        }
        $path = dirname(dirname(__FILE__));
        $file = $path . '/' . str_replace('_', '/', $class);
        $file = $path . '/' . str_replace("\\", '/', $class) . '.php';
        if (!file_exists($file)) {
            throw new \PHPSpec\Exception(
                'include_once("' . $file . '"): file does not exist'
            );
        } else {
            include_once $file;
        }
    }

}

spl_autoload_register(
    array(
        "\\PHPSpec\\Framework",
        'autoload'
    )
);

if (!defined('PHPSPEC_COMMAND_CALL')) {
    try {
        $command = new \PHPSpec\Console\Command;
        $command->run();
    } catch (\PHPSpec\Console\Exception $e) {
        $fp = fopen(STDERR, 'w+');
        fwrite($fp, $e->getMessage());
    }
}

