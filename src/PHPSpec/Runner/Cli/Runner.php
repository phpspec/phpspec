<?php

namespace PHPSpec\Runner\Cli;

use \PHPSpec\World,
    \PHPSpec\Loader\Loader,
    \PHPSpec\Runner\Error,
    \PHPSpec\Runner\Cli\Error as CliError,
    \PHPSpec\Specification\ExampleGroup,
    \PHPSpec\Specification\ExampleRunner,
    \PHPSpec\Runner\Formatter\Factory as FormatterFactory;

class Runner implements \PHPSpec\Runner\Runner
{
    const VERSION = '1.2.0beta';
    
    /**
     * Usage message
     */
    const USAGE = "Usage: phpspec (FILE|DIRECTORY) + [options]
    
    -b, --backtrace                  Enable full backtrace
    -c, --colour, --color            Enable color in the output
    -f, --formater FORMATTER         Choose a formatter
                                       [p]rogress (default - dots)
                                       [d]ocumentation (group and example names)
                                       [h]tml
                                       custom formatter class name
    -h, --help                       You're looking at it
    --fail-fast                      Abort the run on first failure.
    --version                        Show version
";
    
    protected $_loader;
    
    protected $_formatterFactory;
    protected $_exampleRunner;
    
    public function run(World $world)
    {
        if ($this->printVersionOrHelp($world)) {
            return;
        }
        
        $this->setColor($world);
        $this->setBacktrace($world);
        $this->setFailFast($world);
        $this->startErrorHandler();
        $world->getReporter()->setRuntimeStart();

        $this->runExamples($world);
        
        $world->getReporter()->setRuntimeEnd();
        restore_error_handler();
        
    }
    
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
    
    protected function setColor(World $world)
    {
        $formatter = $world->getReporter()->getFormatter();
        if ($world->getOption('c')) {
            $formatter->setShowColors(true);
        }
        $world->getReporter()->attach($formatter);
    }
    
    protected function setBacktrace(World $world)
    {
        if ($world->getOption('b')) {
            $world->getReporter()->getFormatter()->setEnableBacktrace(true);
        }
    }
    
    protected function setFailFast(World $world) {
        if($world->getOption('failfast')) {
            $world->getReporter()->setFailFast(true);
        }
    }
    
    protected function startErrorHandler()
    {
        set_error_handler(
            array('\PHPSpec\Specification\Result', 'errorHandler')
        );
    }
    
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
    
    public function getFormatter(World $world)
    {
        return $this->getFormatterFactory()->create(
            $world->getOption('f'), $world->getReporter()
        );
    }
    
    public function getFormatterFactory()
    {
        if ($this->_formatterFactory === null) {
            $this->_formatterFactory = new FormatterFactory;
        }
        return $this->_formatterFactory;
    }
    
    public function setFormatterFactory(FormatterFactory $factory)
    {
        $this->_formatterFactory = $factory;
    }
    
    public function getExampleRunner()
    {
        if ($this->_exampleRunner === null) {
            $this->_exampleRunner = new ExampleRunner;
        }
        return $this->_exampleRunner;
    }
    
    public function setExampleRunner(ExampleRunner $factory)
    {
        $this->_exampleRunner = $factory;
    }
    
    public function getLoader()
    {
        if ($this->_loader === null) {
            $this->_loader = new Loader;
        }
        return $this->_loader;
    }
    
    public function setLoader(Loader $loader)
    {
        $this->_loader = $loader;
    }
    
    public function getUsage()
    {
        return self::USAGE;
    }
    
    public function getVersion()
    {
        return self::VERSION;
    }
}