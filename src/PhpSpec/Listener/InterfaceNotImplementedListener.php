<?php

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\CodeGenerator\GeneratorManager;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;

use PhpSpec\Exception\Fracture\InterfaceNotImplementedException;

class InterfaceNotImplementedListener implements EventSubscriberInterface
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

        if (!($exception instanceof InterfaceNotImplementedException)) {
            return;
        }

        $this->interfaces[$exception->getInterface()] = get_class($exception->getSubject());
    }

    public function afterSuite(SuiteEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        foreach ($this->interfaces as $interfaceName => $className) {
            try {
                $resource = $this->resources->createResource($className);
            } catch (\RuntimeException $e) {
                continue;
            }

            $message = sprintf('Do you want `%s` to implement `%s`?', $className, $interfaceName);

            $this->io->writeln();
            if ($this->io->askConfirmation($message)) {
                $this->generator->generate($resource, 'implementation', array('interface' => $interfaceName));
            }
        }
    }
}
