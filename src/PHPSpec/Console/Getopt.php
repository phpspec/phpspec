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
    protected $_options = array(
        'noneGiven' => false,
        'c'         => false,
        'color'     => false,
        'colour'    => false,
        'a'         => false,
        'autotest'  => false,
        'h'         => false,
        'help'      => false,
        'version'   => false,
        'reporter'  => 'Console',
        'specFile'  => ''
    );
    
    /**
     * @var array
     */
    protected $_arguments = array();

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
        
        $this->_arguments = $argv;
        
        // FIXME! Work in constructor 
        $this->_parse();
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
        if (!isset($this->_options[trim($name)])) {
            throw new \PHPSpec\Console\Exception("Invalid option $name");
        }
        $this->_options[trim($name)] = $value;
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
     * Parses command line arguments
     */
    protected function _parse()
    { 
        $this->removeProgramNameFromArguments();
        $this->getArgumentsFromConfigFile();
        $this->checkIfArgumentsAreGiven();
        $this->extractSpecFile();
        $this->convertArgumentIntoOptions();
    }
    
    /**
     * Removes phpspec script name from command line argument list
     */
    private function removeProgramNameFromArguments()
    {
        if (is_file($this->_arguments[0])) {
            array_shift($this->_arguments);
        }
    }
    
    /**
     * Gets the arguments from config file, if any
     */
    private function getArgumentsFromConfigFile()
    {
        $currentDirConfig = getcwd() . DIRECTORY_SEPARATOR . '.phpspec';
        $homeDirConfig    = '~' . DIRECTORY_SEPARATOR . '.phpspec';
        $etcDirConfig = DIRECTORY_SEPARATOR . 'etc' .
                        DIRECTORY_SEPARATOR . 'phpspec' .
                        DIRECTORY_SEPARATOR . 'phpspec.conf';
        
        if (file_exists($currentDirConfig)) {
            $this->_arguments = file($currentDirConfig);
        } elseif (file_exists($homeDirConfig)) {
            $this->_arguments = file($homeDirConfig);
        } elseif (file_exists($etcDirConfig)) {
            $this->_arguments = file($etcDirConfig);
        }
    }
    
    /**
     * Checks whether arguments were given
     */
    private function checkIfArgumentsAreGiven()
    {
        if (empty($this->_arguments)) {
            throw new \PHPSpec\Console\Exception(
                'No arguments given. Type phpspec -h for help'
            );
        }
    }

    /**
     * Extracts spec file. If the first argument is not a - or -- option
     * it should be a spec filename
     */
    private function extractSpecFile()
    {
        if ($this->_arguments[0]{0} !== '-') {
            $this->_options['specFile'] = $this->_arguments[0];
            array_shift($this->_arguments);
        }
    }
    
    /**
     * Converts arguments into options
     */
    private function convertArgumentIntoOptions()
    {
        foreach ($this->_arguments as $argument) {
            if (substr($argument, 0, 2) == '--') {
                $this->convertLongArgumentstoOptions($argument);
            } elseif ($argument[0] === '-') {
                $this->convertShortArgumentsToOptions($argument);
            }
        }
    }
    
    /**
     * Converts long arguments, e.g. --argument=value, to options
     * 
     * @param string $argument
     */
    private function convertLongArgumentstoOptions($argument)
    {
        $parts = explode('=', substr($argument, 2));
        if (count($parts) == 2) {
            $this->setOption($parts[0], $parts[1]);
        } elseif (count($parts) == 1) {
            $this->setOption($parts[0], true);
        }
    }
    
    /**
     * Converts short arguments, e.g. -chv, to options
     * 
     * @param string $argument
     */
    private function convertShortArgumentsToOptions($argument)
    {
        $values = str_split(substr($argument, 1));
        foreach ($values as $letter) {
            $this->setOption($letter, true);
        }
    }
}