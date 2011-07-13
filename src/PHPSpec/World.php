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

use \PHPSpec\Runner\Reporter;

class World
{
    protected $options;
    protected $reporter;
    
    public function getOption($name)
    {
        return isset($this->_options[$name]) ? $this->_options[$name] : null;
    }
    
    public function setOption($name, $value)
    {
        if (isset($this->_options[$name])) {
            return $this->_options[$name] = $value;            
        }
        return false;
    }
    
    public function getOptions()
    {
        return $this->_options;
    }
    
    public function setOptions(array $options)
    {
        $this->_options = $options;
    }
    
    public function getReporter()
    {
        return $this->_reporter;
    }
    
    public function setReporter($reporter)
    {
        $this->_reporter = $reporter;
    }
    
    public function loadConfig()
    {
        $ds          = DIRECTORY_SEPARATOR;
        $localConfig =       getcwd() . $ds . '.phpspec';
        $homeConfig  = getenv('HOME') . $ds . '.phpspec';
        $etcConfig   =    $ds . 'etc' . $ds . 'phpspec' . $ds . 'phpspec.conf';
        $configArguments = array();
        
        if (file_exists($localConfig)) {
            $configArguments = file($localConfig);
        } elseif (file_exists($homeConfig)) {
            $configArguments = file($homeConfig);
        } elseif (file_exists($etcConfig)) {
            $configArguments = file($etcConfig);
        }
        
        $this->_options = array_merge($this->_options, $configArguments);
    }
}