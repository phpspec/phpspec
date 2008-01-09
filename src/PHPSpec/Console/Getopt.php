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
class PHPSpec_Console_Getopt
{

    protected $_options = array();

    public function __construct(array $argv = null)
    {
        if (is_null($argv)) {
            $argv = $_SERVER['argv'];
        }
        $this->_parse($argv);
    }

    public function getOption($name)
    {
        return $this->_options[$name];
    }

    protected function __get($name)
    {
        return $this->getOption($name);
    }

    public function setOption($name, $value)
    {
        $this->_options[$name] = $value;
    }

    protected function __set($name, $value)
    {
        $this->setOption($name, $value);
    }

    public function hasOption($name)
    {
        return isset($this->_options[$name]);
    }

    protected function __isset($name)
    {
        return $this->hasOption($name);
    }

    public function getAllOptions()
    {
        return $this->_options;
    }

    public function getAllOptionsAsObject() 
    {
        $options = new stdClass;
        foreach ($this->_options as $key=>$value) {
            $options->$key = $value;
        }
        return $options;
    }

    public static function parseOptionString($str)
    {
        $argv = explode(' ', $str);
        $options = new self($argv);
        return $options;
    }

    protected function _parse(array $argv)
    {
        // get rid of the Command.php reference
        if (is_file($argv[0])) {
            array_shift($argv);
        }

        // if the first argument is not a - or -- option it should be a spec filename
        if ($argv[0][0] !== '-') {
            $this->_options['specFile'] = $argv[0];
            array_shift($argv);
        }

        $encountered = null;
        foreach ($argv as $value) {
            if (!is_null($encountered)) {
                if ($value[0] != '-') {
                    $this->_options[$encountered] = $value;
                    $encountered = null;
                    continue;
                } else {
                    $this->_options[$encountered] = true;
                }
            }
            if (substr($value, 0, 2) == '--') {
                $encountered = substr($value, 2);
                continue;
            } else {
                $values = str_split(substr($value, 1));
                foreach ($values as $letter) {
                    $this->_options[$letter] = true;
                }
            }
        }
        if (!is_null($encountered)) {
            $parts = explode('=', $encountered);
            if (count($parts) == 1) {
                $this->_options[$encountered] = true;
            } elseif (count($parts) == 2) {
                $encountered = $parts[0];
                $this->_options[$encountered] = $parts[1];
            }
        }
    }

}