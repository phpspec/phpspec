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
namespace PHPSpec\Console;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Getopt
{
    /**
     * @var array
     */
    protected $_options = array();

    /**
     * Constructs the object with the argument variables. If none is given it
     * gets them from <code>$_SERVER['argv']</code>. Parses all arguments
     * into the options property
     * 
     * @param array $argv
     */
    public function __construct(array $argv = null)
    {
        if (is_null($argv)) {
            $argv = $_SERVER['argv'];
        }  
        // FIXME! Work in constructor 
        $this->_parse($argv);

        if (!isset($this->_options['reporter'])) {
            $this->_options['reporter'] = 'Console';
        }
    }

    /**
     * Gets an option
     * 
     * @param string $name
     * @return NULL|string
     */
    public function getOption($name)
    {
        return isset($this->_options[$name]) ? $this->_options[$name] : null;
    }

    /**
     * Gets an option with object operator directly
     * 
     * @param string $name
     * @return NULL|string
     */
    public function __get($name)
    {
        return $this->getOption($name);
    }

    /**
     * Sets an option
     * 
     * @param string $name
     * @param string $value
     */
    public function setOption($name, $value)
    {
        $this->_options[$name] = $value;
    }

    /**
     * Sets an option with object operator directly
     * 
     * @param string $name
     * @param string $value
     */
    public function __set($name, $value)
    {
        $this->setOption($name, $value);
    }

    /**
     * Checks if an option has been set
     * 
     * @param string $name
     */
    public function hasOption($name)
    {
        return isset($this->_options[$name]);
    }

    /**
     * Checks if noneGiven flag has been set to true. This flag is set to true
     * in case there is nothing in argv, to stop the parser from doing any
     * unecessary work
     * 
     * @return boolean
     */
    public function noneGiven()
    {
        return $this->_options['noneGiven'] === true;
    }

    /**
     * Checks if an option has been set calling <code>isset</code> on the
     * options array
     * 
     * @param string $name
     */
    public function __isset($name)
    {
        return $this->hasOption($name);
    }

    /**
     * Enter description here ...
     * @param array $argv
     */
    protected function _parse(array $argv)
    { 
      
        // get rid of the Command.php reference
        if (is_file($argv[0])) {
            array_shift($argv);
        }

        $this->_options['noneGiven'] = false;
        if (empty($argv)) {
            $this->_options['noneGiven'] = true;
            return;
        }
                                        
        // if the first argument is not a - or -- option
        // it should be a spec filename
        if ($argv[0]{0} !== '-') {
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