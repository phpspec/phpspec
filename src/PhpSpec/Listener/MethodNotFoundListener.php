<?php

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\CodeGenerator\GeneratorManager;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;

use PhpSpec\Exception\Fracture\MethodNotFoundException;

class MethodNotFoundListener implements EventSubscriberInterface
{
    private $io;
    private $resources;
    private $generator;
    private $methods = array();

    public function __construct(IO $io, ResourceManager $resources, GeneratorManager $generator)
    {
        $this->io        = $io;
        $this->resources = $resources;
        $this->generator = $generator;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite'   => array('afterSuite', -10),
        );
    }

    public function afterExample(ExampleEvent $event)
    {
        if (null === $exception = $event->getException()) {
            return;
        }

        if (!$exception instanceof MethodNotFoundException) {
            return;
        }

		$subject = $exception->getSubject();
			
		$method = $exception->getMethodName();
	
		$reflectedClass = new \ReflectionObject($subject);

		if (!$reflectedClass->hasMethod($method)) {
	        $this->methods[$reflectedClass->getName().'::'.$method] = $exception->getArguments();
		}
		
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }
        
        foreach ($this->methods as $call => $arguments) {
            list($classname, $method) = explode('::', $call);
            $message = sprintf('Do you want me to create `%s()` for you?', $call);

            try {
                $resource = $this->resources->createResource($classname);
            } catch (\RuntimeException $e) {
                continue;
            }

            $this->io->writeln();
            if ($this->io->askConfirmation($message)) {
                $this->generator->generate($resource, 'method', array(
                    'name'      => $method,
                    'arguments' => $arguments
                ));
            }
        }
    }
}
