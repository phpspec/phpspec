<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Process\RerunContext;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FatalSkippingListener implements EventSubscriberInterface
{

    /**
     * @var OutputInterface
     */
    private $output;
    /**
     * @var RerunContext
     */
    private $context;

    /**
     * @var array
     */
    private $fatalSpecs = array();

    /**
     * @var int
     */
    private $verbosity;

    public function __construct(OutputInterface $output, RerunContext $context)
    {
        $this->output = $output;
        $this->context = $context;
    }

    public function beforeSuite(SuiteEvent $event)
    {
        if ($this->fatalSpecs = $this->context->listFatalSpecs()) {
            $this->verbosity = $this->output->getVerbosity();
            $this->output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }
    }

    public function beforeExample(ExampleEvent $event)
    {
        $spec = $event->getExample()->getFunctionReflection()->getDeclaringClass()->getName();
        $example = $event->getExample()->getFunctionReflection()->getName();

        $specDef = array($spec, $example);

        if (($key = array_search($specDef, $this->fatalSpecs)) !== false) {
            unset($this->fatalSpecs[$key]);

            if (!$this->fatalSpecs) {
                $this->output->setVerbosity($this->verbosity);
            }
        }
    }

    /*
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
          'beforeSuite' => 'beforeSuite',
          'beforeExample' => 'beforeExample'
        );
    }
}
