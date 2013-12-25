<?php

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\CodeGenerator\GeneratorManager;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;

use PhpSpec\Exception\Fracture\InterfaceNotFoundException;

class InterfaceNotFoundListener implements EventSubscriberInterface
{
    private $io;
    private $resources;
    private $generator;
    private $interfaces = array();

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

        if (!($exception instanceof InterfaceNotFoundException)) {
            return;
        }

        $this->interfaces[$exception->getInterface()] = true;
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach (array_keys($this->interfaces) as $interfaceName) {
            $message = sprintf('Do you want me to create `%s` for you?', $interfaceName);

            try {
                $resource = $this->resources->createResource($interfaceName);
            } catch (\RuntimeException $e) {
                continue;
            }

            $this->io->writeln();
            if ($this->io->askConfirmation($message)) {
                $this->generator->generate($resource, 'interface');
            }
        }
    }
}
