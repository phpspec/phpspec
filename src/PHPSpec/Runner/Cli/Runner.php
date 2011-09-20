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
namespace PHPSpec\Runner\Cli;

use \PHPSpec\World,
    \PHPSpec\Loader\Loader,
    \PHPSpec\Runner\Error,
    \PHPSpec\Runner\Cli\Error as CliError,
    \PHPSpec\Specification\ExampleGroup,
    \PHPSpec\Specification\ExampleRunner,
    \PHPSpec\Runner\Formatter\Factory as FormatterFactory;

/**
 * @category   PHPSpec
 * @package    PHPSpec
 * @copyright  Copyright (c) 2007-2009 Pádraic Brady, Travis Swicegood
 * @copyright  Copyright (c) 2010-2011 Pádraic Brady, Travis Swicegood,
 *                                     Marcello Duarte
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU Lesser General Public Licence Version 3
 */
class Runner implements \PHPSpec\Runner\Runner
{
    /**
     * Version
     * 
     * @var string
     */
    const VERSION = '1.2.2beta';
    
    /**
     * Usage message
     */
    const USAGE = "Usage: phpspec (FILE|DIRECTORY) + [options]
    
    -b, --backtrace          Enable full backtrace
    -c, --colour, --color    Enable color in the output
    -e, --example STRING     Run examples whose full nested names include STRING
    -f, --formater FORMATTER Choose a formatter
                              [p]rogress (default - dots)
                              [d]ocumentation (group and example names)
                              [h]tml
                              custom formatter class name
    --bootstrap FILENAME     Specify a bootstrap file to run before the tests
    -h, --help               You're looking at it
    --fail-fast              Abort the run on first failure.
    --version                Show version
";
    
    
    /**
     * The loader
     *
     * @var \PHPSpec\Loader\Loader
     */
    protected $_loader;
    
    
    /**
     * The formatter factory
     *
     * @var \PHPSpec\Runner\Formatter\Factory
     */
    protected $_formatterFactory;
    
    /**
     * The example runner
     *
     * @var \PHPSpec\Specification\ExampleRunner
     */
    protected $_exampleRunner;
    
    /**
     * Sets options and runs examples; or prints version/help
     * 
     * @param \PHPSpec\World $world
     */
    public function run(World $world)
    {
        if ($this->printVersionOrHelp($world)) {
            return;
        }
        
        $this->setColor($world);
        $this->setBacktrace($world);
        $this->setFailFast($world);
        $this->setExampleIntoRunner($world);
        
        $this->startErrorHandler();
        $world->getReporter()->setRuntimeStart();

        $this->bootstrap($world);
        
        $this->runExamples($world);
        
        $world->getReporter()->setRuntimeEnd();
        restore_error_handler();
    }
    
    /**
     * Runs examples
     * 
     * @param \PHPSpec\World $world
     */
    protected function runExamples(World $world)
    {
        $examples = $this->getExamples($world);
        foreach ($examples as $exampleGroup) {
            $exampleGroup->beforeAll();
            $this->getExampleRunner()->run(
                $exampleGroup, $world->getReporter()
            );
            $exampleGroup->afterAll();
        }
    }
    
    /**
     * Sets the color into the formatter
     * 
     * @param \PHPSpec\World $world
     */
    protected function setColor(World $world)
    {
        $formatter = $world->getReporter()->getFormatter();
        if ($world->getOption('c')) {
            $formatter->setShowColors(true);
        }
        $world->getReporter()->attach($formatter);
    }
    
    /**
     * Sets the backtrace into the formatter
     * 
     * @param \PHPSpec\World $world
     */
    protected function setBacktrace(World $world)
    {
        if ($world->getOption('b')) {
            $world->getReporter()->getFormatter()->setEnableBacktrace(true);
        }
    }
    
    /**
     * Sets fail fast into the reporter
     * 
     * @param \PHPSpec\World $world
     */
    protected function setFailFast(World $world)
    {
        if ($world->getOption('failfast')) {
            $world->getReporter()->setFailFast(true);
        }
    }
    
    /**
     * Sets one example into the example runner
     * 
     * @param \PHPSpec\World $world
     */
    protected function setExampleIntoRunner(World $world)
    {
        if ($world->getOption('example')) {
            $this->getExampleRunner()->runOnly($world->getOption('example'));
        }
    }
    
    /**
     * Starts error handler
     */
    protected function startErrorHandler()
    {
        set_error_handler(
            array('\PHPSpec\Specification\Result', 'errorHandler')
        );
    }
    
    /**
     * Sends a message to reporter to print help or version if needed.
     * Returns true if message was sent and false otherwise
     * 
     * @param \PHPSpec\World $world
     * @return boolean
     */
    private function printVersionOrHelp(World $world)
    {
        if ($world->getOption('version')) {
            $world->getReporter()->setMessage(self::VERSION);
            return true;
        }
        
        if ($world->getOption('h') || $world->getOption('help')) {
            $world->getReporter()->setMessage(self::USAGE);
            return true;
        }
        
        return false;
    }
    
    /**
     * Loads and returns example groups
     * 
     * @param \PHPSpec\World $world
     * @throws \PHPSpec\Runner\Cli\Error if no spec file is given
     * @return array<\PHPSpec\Specification\ExampleGroup>
     */
    private function getExamples(World $world)
    {
        if ($world->getOption('specFile')) {
            $exampleGroups = $this->loadExampleGroups(
                $world->getOption('specFile')
            );
        } else {
            throw new CliError('No spec file given');
        }
        return $exampleGroups;
    }
    
    /**
     * Loads example groups
     * 
     * @param string $spec
     * @throws \PHPSpec\Runner\Cli\Error propagate cli errors up
     * @return array<\PHPSpec\Specification\ExampleGroup>
     */
    private function loadExampleGroups($spec)
    {
        try {
            $exampleGroups = array();
            $loader = $this->getLoader()->factory($spec);
            return $loader->load($spec);
        } catch (Error $e) {
            throw new CliError($e->getMessage());
        }
    }
    
    /**
     * Gets formatter
     * 
     * @param \PHPSpec\World $world
     */
    public function getFormatter(World $world)
    {
        return $this->getFormatterFactory()->create(
            $world->getOption('f'), $world->getReporter()
        );
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
     * Gets the example runner
     * 
     * @return \PHPSpec\Specification\ExampleRunner
     */
    public function getExampleRunner()
    {
        if ($this->_exampleRunner === null) {
            $this->_exampleRunner = new ExampleRunner;
        }
        return $this->_exampleRunner;
    }
    
    /**
     * Sets the example runner
     * 
     * @param \PHPSpec\Specification\ExampleRunner $factory
     */
    public function setExampleRunner(ExampleRunner $factory)
    {
        $this->_exampleRunner = $factory;
    }
    
    /**
     * Gets the loader
     * 
     * @return \PHPSpec\Loader\Loader
     */
    public function getLoader()
    {
        if ($this->_loader === null) {
            $this->_loader = new Loader;
        }
        return $this->_loader;
    }
    
    /**
     * Sets the loader
     *
     * @param \PHPSpec\Loader\Loader $loader
     */
    public function setLoader(Loader $loader)
    {
        $this->_loader = $loader;
    }
    
    /**
     * Loads the bootstrap file if specified in the options
     */
    public function bootstrap(\PHPSpec\World $world) {
        $bootstrap_file = $world->getOption('bootstrap');
        
        if (empty($bootstrap_file)) {
            return;
        }
        
        if (!file_exists($bootstrap_file) || !is_readable($bootstrap_file)) {
            throw new \PHPSpec\Exception('Cannot load specified bootstrap file: ' . $bootstrap_file);
        }
        
        include $bootstrap_file;
    }
    
    /**
     * Gets usage
     * 
     * @return string
     */
    public function getUsage()
    {
        return self::USAGE;
    }
    
    /**
     * Gets version
     * 
     * @return string
     */
    public function getVersion()
    {
        return self::VERSION;
    }
}
