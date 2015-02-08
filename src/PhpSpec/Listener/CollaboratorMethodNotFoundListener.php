<?php

namespace PhpSpec\Listener;

use PhpSpec\CodeGenerator\GeneratorManager;
use PhpSpec\Console\IO;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Locator\ResourceManagerInterface;
use Prophecy\Exception\Doubler\MethodNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CollaboratorMethodNotFoundListener implements EventSubscriberInterface
{
    const PROMPT = 'Would you like me to generate a method signature `%s::%s()` for you?';
    /**
     * @var IO
     */
    private $io;

    /**
     * @var array
     */
    private $interfaces = array();

    /**
     * @var ResourceManagerInterface
     */
    private $resources;

    /**
     * @var GeneratorManager
     */
    private $generator;

    /**
     * @param IO $io
     * @param ResourceManagerInterface $resources
     * @param GeneratorManager $generator
     */
    public function __construct(IO $io, ResourceManagerInterface $resources, GeneratorManager $generator)
    {
        $this->io = $io;
        $this->resources = $resources;
        $this->generator = $generator;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', 10),
            'afterSuite' => array('afterSuite', -10)
        );
    }

    /**
     * @param ExampleEvent $event
     */
    public function afterExample(ExampleEvent $event)
    {
        if (!$this->io->isCodeGenerationEnabled()) {
            return;
        }

        if (!($exception = $event->getException()) || !$exception instanceof MethodNotFoundException) {
            return;
        }

        $classname = $exception->getClassname();

        if (class_parents($classname) !== array('stdClass'=>'stdClass')) {
            return;
        }

        $interfaces = array_filter(class_implements($classname),
            function ($interface) { return !preg_match('/^Prophecy/', $interface); }
        );

        if (count($interfaces) !== 1) {
            return;
        }

        $interface = current($interfaces);

        if (!array_key_exists($interface, $this->interfaces)) {
            $this->interfaces[$interface] = array();
        }

        $this->interfaces[$interface][$exception->getMethodName()] = $exception->getMethodName();
    }

    public function afterSuite(SuiteEvent $event)
    {
        foreach ($this->interfaces as $interface => $methods) {

            try {
                $resource = $this->resources->createResource($interface);
            }
            catch (\RuntimeException $e) {
                continue;
            }

            foreach ($methods as $method) {
                if ($this->io->askConfirmation(sprintf(self::PROMPT, $interface, $method))) {
                    $this->generator->generate(
                        $resource,
                        'method-signature',
                        array('name' => $method)
                    );
                    $event->markAsWorthRerunning();
                }
            }
        }
    }
}
