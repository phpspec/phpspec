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
 * @copyright Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                    Marcello Duarte
 * @license   http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
namespace PHPSpec;

use \PHPSpec\Runner\Runner,
    \PHPSpec\Runner\Reporter,
    \PHPSpec\Runner\ReporterEvent,
    \PHPSpec\Runner\Parser,
    \PHPSpec\World,
    \PHPSpec\Runner\Cli\Parser as CliParser,
    \PHPSpec\Runner\Cli\Runner as CliRunner,
    \PHPSpec\Runner\Cli\Reporter as CliReporter,
    \PHPSpec\Runner\Cli\Configuration,
    \PHPSpec\Runner\Formatter\Factory as FormatterFactory,
    \PHPSpec\Runner\Formatter;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2012 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class PHPSpec
{
    
    /**
     * Raw arguments from argv
     *
     * @var array
     */
    protected $_arguments;
    
    /**
     * The parser
     *
     * @var \PHPSpec\Runner\Parser
     */
    protected $_parser;
    
    /**
     * The reporter object
     *
     * @var \PHPSpec\Runner\Reporter
     */
    protected $_reporter;
    
    /**
     * The runner object
     *
     * @var \PHPSpec\Runner\Runner
     */
    protected $_runner;
    
    /**
     * Keeps the environment data, mainly options and reporter
     *
     * @var \PHPSpec\World
     */
    protected $_world;
    
    /**
     * The formatter factory
     *
     * @var \PHPSpec\Runner\Formatter\Factory
     */
    protected $_formatterFactory;
    
    /**
     * The configuration
     *
     * @var \PHPSpec\Runner\Cli\Configuration
     */
    protected $_configuration;
    
    /**
     * Whether we are testing PHPSpec itself
     * 
     * @var bool
     */
    protected static $_testingPHPSpec = false;
    
    /**
     * PHPSpec is constructed with arguments
     * 
     * @param array $argv
     */
    public function __construct(array $argv = array())
    {
        $this->_arguments = $argv;
        list (
            $this->_parser, $this->_runner, $this->_reporter, $this->_world
        ) = $this->inlineFactory(
            array(
                'Parser', 'Runner', 'Reporter', 'World'
            )
        );
    }
    
    /**
     * Executes PHPSpec and outputs the result 
     */
    public function execute()
    {
        try {
            $this->loadAndRun();
        } catch (\PHPSpec\Runner\Error $e) {
            $this->_reporter->setMessage($e->getMessage());
        }
        $this->output();
    }
    
    /**
     * Loads options with the parser into the world. If there are no options
     * then sends message to show usage
     */
    protected function loadAndRun()
    {
        $options = $this->parseOptionsAndSetWorld();
        $this->setFormatter($this->_world);
        if ($options !== null) {
            $this->setDefaultBootstrap($this->_world);
            $this->_runner->run($this->_world);
        } else {
            $this->showUsage();
        }
    }

    /**
     * Sends message to Formatter so it starts output
     */
    protected function output()
    {
        $this->makeSureWeHaveAFormatter();
        $this->_reporter->notify(new ReporterEvent('exit', '', ''));
    }
    
    /**
     * Parses options into World
     * 
     * @return array|null
     */
    protected function parseOptionsAndSetWorld()
    {
        $this->_world->setReporter($this->_reporter);
        $configOptions = $this->getConfiguration()->load();
        $arguments = array_merge($this->_arguments, $configOptions);
        $options = $this->getParser()->parse($arguments);
        $this->_world->setOptions($options);
        return $options;
    }
    
    /**
     * Looks for a SpecHelper.php in case the bootstrap option is empty and
     * add that to world's options
     *
     * @param World $world 
     */
    protected function setDefaultBootstrap(World $world)
    {
        $specHelper = $world->getOption('specFile') . DIRECTORY_SEPARATOR .
                      'SpecHelper.php';
        if (!$world->getOption('bootstrap') &&
            is_dir($world->getOption('specFile')) &&
            file_exists($specHelper)) {
            $world->setOption('bootstrap', $specHelper);
        }
    }
    
    /**
     * Asserts we have a formatter and create one if we don't 
     */
    private function makeSureWeHaveAFormatter()
    {
        if (!count($this->_reporter->getFormatters())) {
            $this->_world->setOptions(array('formatter' => 'p'));
            $this->setFormatter();
        }
    }
    
    /**
     * Sends a message to the reporter to show message
     */
    protected function showUsage()
    {
        $this->_reporter->setMessage($this->_runner->getUsage());
    }
    
    /**
     * Gets the parser
     * 
     * @return \PHPSpec\Runner\Parser
     */
    public function getParser()
    {
        if ($this->_parser === null) {
            $this->_parser = new CliParser;
        }
        return $this->_parser;
    }
    
    /**
     * Gets the reporter
     * 
     * @return \PHPSpec\Runner\Reporter
     */
    public function getReporter()
    {
        if ($this->_reporter === null) {
            $this->_reporter = new CliReporter;
        }
        return $this->_reporter;
    }
    
    /**
     * Gets the runner
     * 
     * @return \PHPSpec\Runner\Runner
     */
    public function getRunner()
    {
        if ($this->_runner === null) {
            $this->_runner = new CliRunner;
        }
        return $this->_runner;
    }
    
    /**
     * Gets the workd
     * 
     * @return \PHPSpec\World
     */
    public function getWorld()
    {
        if ($this->_world === null) {
            $this->_world = new World;
        }
        return $this->_world;
    }
    
    /**
     * Gets the configuration
     * 
     * @return \PHPSpec\Runner\Cli\Configuration
     */
    public function getConfiguration()
    {
        if ($this->_configuration === null) {
            $this->_configuration = new Configuration;
        }
        return $this->_configuration;
    }
    
    /**
     * Sets the parser
     * 
     * @param \PHPSpec\Runner\Parser $parser
     */
    public function setParser(Parser $parser)
    {
        $this->_parser = $parser;
    }
    
    /**
     * Sets the reporter
     * 
     * @param \PHPSpec\Runner\Reporter $reporter
     */
    public function setReporter(Reporter $reporter)
    {
        $this->_reporter = $reporter;
    }
    
    /**
     * Sets the runner
     * 
     * @param \PHPSpec\Runner\Runner $runner
     */
    public function setRunner(Runner $runner)
    {
        $this->_runner = $runner;
    }
    
    /**
     * Sets the environment
     * 
     * @param \PHPSpec\World $world
     */
    public function setWorld(World $world)
    {
        $this->_world = $world;
    }
    
    /**
     * Gets the formatter from world and register it into the reporter
     */
    protected function setFormatter()
    {
        $formatterOption = $this->_world->getOption('formatter');
        $formatter = $this->getFormatterFactory()->create(
            $formatterOption, $this->_world->getReporter()
        );
        $this->_world->getReporter()->addFormatter($formatter);
    }
    
    /**
     * Gets the formatter factory
     * 
     * @return \PHPSpec\Runner\Formatter\Factory
     */
    public function getFormatterFactory()
    {
        if ($this->_formatterFactory === null) {
            $this->_formatterFactory = new FormatterFactory;
        }
        return $this->_formatterFactory;
    }
    
    /**
     * Sets the formatter factory
     * 
     * @param \PHPSpec\Runner\Formatter\Factory $factory
     */
    public function setFormatterFactory(FormatterFactory $factory)
    {
        $this->_formatterFactory = $factory;
    }
    
    /**
     * Inline factory pattern
     * 
     * @param array $classes
     * @return array
     */
    protected function inlineFactory(array $classes)
    {
        $objects = array();
        foreach ($classes as $class) {
            $objects[] = $this->{"get$class"}();
        }
        return $objects;
    }
    
    /**
     * Whether we are testing PHPSpec itself 
     * 
     * @return boolean
     */
    public static function testingPHPSpec()
    {
        return self::$_testingPHPSpec;
    }
    
    /**
     * Sets the testing PHPSpec flag
     * 
     * @param boolean $flag
     */
    public static function setTestingPHPSpec($flag = true)
    {
        self::$_testingPHPSpec = $flag;
    }
}