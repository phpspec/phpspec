<?php

namespace PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\MethodCallEvent;
use PhpSpec\Exception\Example\NotEqualException;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\Util\MethodAnalyser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MethodReturnedNullListener implements EventSubscriberInterface
{
    /**
     * @var \PhpSpec\Console\IO
     */
    private $io;

    /**
     * @var MethodCallEvent[]
     */
    private $nullMethods = array();

    /**
     * @var MethodCallEvent|null
     */
    private $lastMethodCallEvent = null;
    /**
     * @var \PhpSpec\Locator\ResourceManager
     */
    private $resources;
    /**
     * @var \PhpSpec\CodeGenerator\GeneratorManager
     */
    private $generator;
    /**
     * @var MethodAnalyser
     */
    private $methodAnalyser;

    /**
     * @param IO $io
     * @param ResourceManager $resources
     * @param GeneratorManager $generator
     */
    public function __construct(IO $io, ResourceManager $resources, GeneratorManager $generator, MethodAnalyser $methodAnalyser)
    {
        $this->io = $io;
        $this->resources = $resources;
        $this->generator = $generator;
        $this->methodAnalyser = $methodAnalyser;
    }

    /**
     * @{inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -20),
            'afterMethodCall' => array('afterMethodCall')
        );
    }

    public function afterMethodCall(MethodCallEvent $methodCallEvent)
    {
        $this->lastMethodCallEvent = $methodCallEvent;
    }

    public function afterExample(ExampleEvent $exampleEvent)
    {
        $exception = $exampleEvent->getException();

        if (!$exception instanceof NotEqualException) {
            return;
        }

        if ($exception->getActual() !== null) {
            return;
        }

        if (
            is_object($exception->getExpected())
         || is_array($exception->getExpected())
         || is_resource($exception->getExpected())
        ) {
            return;
        }

        if (!$this->lastMethodCallEvent) {
            return;
        }

        $class = get_class($this->lastMethodCallEvent->getSubject());
        $method = $this->lastMethodCallEvent->getMethod();

        if (!$this->methodAnalyser->methodIsEmpty($class, $method)) {
            return;
        }

        $this->nullMethods[$class.'::'.$method] = array(
            'class'=> $class,
            'method' => $method,
            'expected' => $exception->getExpected()
        );
    }

    public function afterSuite()
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->nullMethods as $methodString => $failedCall) {
            $message = sprintf('Would you like me to make %s() always return %s?', $methodString, $failedCall['expected']);

            try {
                $resource = $this->resources->createResource($failedCall['class']);
            }
            catch (\RuntimeException $exception) {
                continue;
            }

            if($this->io->askConfirmation($message)) {
                $this->generator->generate(
                    $resource, 'returnConstant',
                    array('method'=>$failedCall['method'], 'expected'=>$failedCall['expected'])
                );
            }
        }
    }
}

