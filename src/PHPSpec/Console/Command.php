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

/** @see PHPSpec\Framework */
require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'Framework.php';

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Command
{
    /**
     * @var \PHPSpec\Extensions\Autotest
     */
    protected $_autotest;
    
    /**
     * @var \PHPSpec\Runner
     */
    protected $_runner;
    
    /**
     * @var \PHPSpec\Console\Getopt
     */
    protected $_options;
    
    /**
     * Usage message
     */
    const USAGE = "Usage: phpspec (FILE|DIRECTORY) + [options]
    -c, --colour, --color            Show coloured (red/green) output
    -a, --autospec                   Run all tests continually, every 10 seconds
";

    /**
     * @param \PHPSpec\Console\Getopt $options
     */
    public function __construct(\PHPSpec\Console\Getopt $options = null)
    {
        if (is_null($options)) {
            $options = new \PHPSpec\Console\Getopt;
        }
        $this->_options = $options;
    }

    /**
     * Gets the cli options, passes it to the runner and runs
     */
    public function run()
    {
        if ($this->_options->noneGiven()) {
            $this->printUsage();
            return;
        }
        
        if ($this->_options->getOption('a') ||
            $this->_options->getOption('autotest')) {
            $this->getAutotest()->run($this);
            return;
        }
 
        $this->getRunner()->run($this->_options);
    }
    
    /**
     * Creates an autotest if none given (inline factory)
     * 
     * @return \PHPSpec\Extensions\Autotest
     */
    public function getAutotest()
    {
        if (!$this->_autotest instanceof \PHPSpec\Extensions\Autotest) {
            $this->_autotest = new \PHPSpec\Extensions\Autotest;
        }
        return $this->_autotest;
    } 
    
    /**
     * Allows for autotest injection
     * 
     * @param \PHPSpec\Extensions\Autotest $autotest
     */
    public function setAutotest(\PHPSpec\Extensions\Autotest $autotest)
    {
        $this->_autotest = $autotest;
    }

    /**
     * Creates a runner if none given (inline factory)
     * 
     * @return \PHPSpec\Runner
     */ 
    public function getRunner()
    {
        if (!$this->_runner instanceof \PHPSpec\Runner) {
            $this->_runner = new \PHPSpec\Runner;
        }
        return $this->_runner;
    } 

    /**
     * Allows for runner injection
     * 
     * @param \PHPSpec\Runner $runner
     */
    public function setRunner(\PHPSpec\Runner $runner)
    {
        $this->_runner = $runner;
    }

    /**
     * Prints the cli usage help message
     */
    public function printUsage()
    {
        echo self::USAGE;
    }
    
}