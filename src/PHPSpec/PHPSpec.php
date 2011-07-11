<?php

namespace PHPSpec;

use \PHPSpec\Runner\Runner,
    \PHPSpec\Runner\Reporter,
    \PHPSpec\Runner\Parser,
    \PHPSpec\World,
    \PHPSpec\Runner\Cli\Parser as CliParser,
    \PHPSpec\Runner\Cli\Runner as CliRunner,
    \PHPSpec\Runner\Cli\Reporter as CliReporter,
    \PHPSpec\Runner\Formatter\Factory as FormatterFactory,
    \PHPSpec\Runner\Formatter;

class PHPSpec
{
    protected $_arguments;
    protected $_parser;
    protected $_reporter;
    protected $_runner;
    protected $_world;
    protected $_formatterFactory;
    
    protected static $_testingPHPSpec = false;
    
    public function __construct(array $argv = array())
    {
        $this->_arguments = $argv;
        list (
            $this->_parser, $this->_runner, $this->_reporter, $this->_world
        ) = $this->inlineFactory(array(
            'Parser', 'Runner', 'Reporter', 'World'
        ));
    }
    
    public function execute()
    {
        try {
            $this->loadAndRun();
        } catch (\PHPSpec\Runner\Error $e) {
            $this->_reporter->setMessage($e->getMessage());
        }
        $this->output();
    }
    
    protected function loadAndRun()
    {
        $options = $this->parseOptionsAndSetWorld();
        $this->setFormatter($this->_world);
        if ($options !== null) {
            $this->_runner->run($this->_world);
        } else {
            $this->showUsage();
        }
    }

    protected function output()
    {
        $this->makeSureWeHaveAFormatter();
        $this->_reporter->getFormatter()->output();
    }
    
    protected function parseOptionsAndSetWorld()
    {
        $this->_world->setReporter($this->_reporter);
        $options = $this->_parser->parse($this->_arguments);
        $this->_world->setOptions($options);
        $this->_world->loadConfig();
        return $options;
    }
    
    private function makeSureWeHaveAFormatter()
    {
        if (!$this->_reporter->getFormatter() instanceof Formatter) {
            $this->_world->setOptions(array('formatter' => 'p'));
            $this->setFormatter();
        }
    }
    
    protected function showUsage()
    {
        $this->_reporter->setMessage($this->_runner->getUsage());
    }
    
    public function getParser()
    {
        if ($this->_parser === null) {
            $this->_parser = new CliParser;
        }
        return $this->_parser;
    }
    
    public function getReporter()
    {
        if ($this->_reporter === null) {
            $this->_reporter = new CliReporter;
        }
        return $this->_reporter;
    }
    
    public function getRunner()
    {
        if ($this->_runner === null) {
            $this->_runner = new CliRunner;
        }
        return $this->_runner;
    }
    
    public function getWorld()
    {
        if ($this->_world === null) {
            $this->_world = new World;
        }
        return $this->_world;
    }
    
    public function setParser(Parser $parser)
    {
        $this->_parser = $parser;
    }
    
    public function setReporter(Reporter $reporter)
    {
        $this->_reporter = $reporter;
    }
    
    public function setRunner(Runner $runner)
    {
        $this->_runner = $runner;
    }
    
    public function setWorld(World $world)
    {
        $this->_world = $world;
    }
    
    protected function setFormatter()
    {
        $formatterOption = $this->_world->getOption('formatter');
        $formatter = $this->getFormatterFactory()->create(
            $formatterOption, $this->_world->getReporter()
        );
        $this->_world->getReporter()->setFormatter($formatter);
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
    
    protected function inlineFactory(array $classes)
    {
        $objects = array();
        foreach ($classes as $class) {
            $objects[] = $this->{"get$class"}();
        }
        return $objects;
    }
    
    public static function testingPHPSpec()
    {
        return self::$_testingPHPSpec;
    }
    
    public static function setTestingPHPSpec($flag = true)
    {
        self::$_testingPHPSpec = $flag;
    }
}