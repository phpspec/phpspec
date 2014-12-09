<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Process\ErrorHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ErrorHandlerListener implements EventSubscriberInterface
{
    /**
     * @var ErrorHandler
     */
    private $handler;

    /**
     * @param ErrorHandler $handler
     */
    public function __construct(ErrorHandler $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @param ExampleEvent $event
     */
    public function beforeExample(ExampleEvent $event)
    {
        $function = $event->getExample()->getFunctionReflection();
        $class = $function->getDeclaringClass();

        $this->handler->setCurrentExample($class->getName(), $function->getName());
    }

    public function afterExample()
    {
        $this->handler->clearCurrentExample();
    }

    public static function getSubscribedEvents()
    {
        return array(
            'beforeExample' => 'beforeExample',
            'afterExample' => 'afterExample'
        );
    }
}
