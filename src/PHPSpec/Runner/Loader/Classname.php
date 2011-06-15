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
namespace PHPSpec\Runner\Loader;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Classname
{

    /**
     * Array with the instances of the loaded examples
     * 
     * @var array
     */
    protected $_loaded = array();
    
    /**
     * The name of the spec class
     * 
     * @var string
     */
    protected $_class;
    
    /**
     * The name of the spec file
     * 
     * @var string
     */
    protected $_classFile;
    
    /**
     * The spec name passed in the command line
     * 
     * @var string
     */
    protected $_spec;
    
    /**
     * Path in the FS where spec file is located
     * 
     * @var string
     */
    protected $_pathToFile;
    
    /**
     * Creates the loader based on the spec given as argument and the path to
     * file
     * 
     * @param string $spec
     * @param string $pathToFile
     */
    public function __construct($spec, $pathToFile)
    {
        $this->_spec = $spec;
        $this->_pathToFile = $pathToFile;
    }

    /**
     * Loads classes
     * 
     * @throws \PHPSpec\Exception
     * @return array
     */
    public function load()
    {
        $this->setClassAndClassFile();
        
        // existence test not implemented - let require call catch fatal error
        if (!is_readable($this->_pathToFile . '/' . $this->_classFile)) {
            die(
                'phpspec: cannot open ' .
                $this->_pathToFile . '/' . $this->_classFile . PHP_EOL
            );
        }
        require_once $this->_pathToFile . '/' . $this->_classFile;
        
        if (!class_exists($this->_class, false)) {
            throw new \PHPSpec\Exception(
                'The class ' . $this->_class .
                ' is not defined within the spec file ' . $this->_classFile
            );
        }

        $classReflected = new \ReflectionClass($this->_class);

        $this->_loaded = array($classReflected);

        return $this->_loaded;
    }
    
    /**
     * Sets the class and class file based on the spec argument
     * 
     * Convention; For loading spec files and classes on command line
     * 
     * Convention #1: Specs are reflected in Filenames which follow the
     * format of "Describe*", e.g. "DescribeNewBowlingGame" defined in
     * "DescribeNewBowlingGame.php".
     * 
     * Convention #2: Specs are reflected in the Filename by removing
     * the "Describe" prefix and appending a "Spec" suffix, e.g.
     * "DescribeNewBowlingGame" defined in "NewBowlingGameSpec.php".
     * 
     * Conventions are case sensitive. Both Spec and Describe are expected
     * to commence with a capital letter. On the command line, the .php
     * prefix is optional.
     * 
     * @throws \PHPSpec\Exception
     */
    private function setClassAndClassFile()
    {
        if ($this->isPhpFile()) {
            
            $this->_classFile = $this->_spec;
            
            if ($this->endsWithExtensionAndSpec()) {
                $this->_class = 'Describe' . $this->stripExtensionAndSpec();
                
            } else {
                $this->_class = $this->stripExtension();
            }
            
        } else {
            
            $this->_classFile = $this->_spec . '.php';
            $this->_class     = $this->_spec;
            
            if ($this->endsWithSpec()) {
                $this->_class = 'Describe' . $this->stripSpec();
            }
        }
        
        $this->assertClassStartsWithDescribe();
    }

    /**
     * Checks whether class starts with "Describe"
     * 
     * @return boolean
     */
    private function classStartsWithDescribe()
    {
        return strpos($this->_class, 'Describe') === 0;
    }
    
    /**
     * Checks whether class will not start with "Describe"
     * 
     * @return boolean
     */
    private function classDoesNotStartWithDescribe()
    {
        return !$this->startsWithDescribe();
    }
    
    /**
     * Checks whether spec starts with "Describe" and throw an exception if not
     * 
     * @throws \PHPSpec\Exception
     */
    private function assertClassStartsWithDescribe()
    {
        if ($this->classDoesNotStartWithDescribe()) {
            throw new \PHPSpec\Exception(
                'Invalid class or filename given for a spec; ' .
                'spec could not be found using "' . $this->_spec . '"'
            );
        }
    }

    /**
     * Checks whether spec ends with "Spec.php"
     * 
     * @return boolean
     */
    private function endsWithExtensionAndSpec()
    {
        return substr($this->_spec, -8) === 'Spec.php';
    }
    
    /**
     * Checks whether spec ends with "Spec"
     * 
     * @return boolean
     */
    private function endsWithSpec()
    {
        return substr($this->_spec, -4) === 'Spec';
    }
    
    /**
     * Checks whether spec does not end with "Spec"
     * 
     * @return boolean
     */
    private function doesNotEndWithSpec()
    {
        return !$this->endsWithSpec();
    }

    /**
     * Checks whether spec is a PHP file
     * 
     * @return boolean
     */
    private function isPhpFile()
    {
        return substr($this->_spec, -4) === '.php';
    }

    /**
     * Checks whether spec is not a PHP file
     * 
     * @return boolean
     */
    private function NotAPhpFile()
    {
        return !$this->isPhpFile();
    }

    /**
     * Removes the .php from the spec file
     * 
     * @return string
     */
    private function stripExtension()
    {
        if ($this->isPhpFile()) {
            return substr($this->_spec, 0, strlen($this->_spec) - 4);
        }
        return $this->_spec;
    }
    
    /**
     * Removes the .php from the spec file
     * 
     * @return string
     */
    private function stripExtensionAndSpec()
    {
        if (substr($this->_spec, -8) === 'Spec.php') {
            return substr($this->_spec, 0, strlen($this->_spec) - 8);
        }
        return $this->_spec;
    }

    /**
     * Removes the Spec from the spec file
     * 
     * @return string
     */
    private function stripSpec()
    {
        if ($this->endsWithSpec()) {
            return substr($this->_spec, 0, strlen($this->_spec) - 4);
        }
        return $this->_spec;
    }

    /**
     * Gets the loaded examples in array
     * 
     * @return array
     */
    public function getLoaded()
    {
        return $this->_loaded;
    }

}