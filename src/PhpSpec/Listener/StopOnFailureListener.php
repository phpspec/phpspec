<?php

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\StopOnFailureException;
use Symfony\Component\Console\Input\InputInterface;

class StopOnFailureListener implements EventSubscriberInterface
{
    private $input;

    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }
    
    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', -100),
        );
    }

    public function afterExample(ExampleEvent $event)
    {        
        if (!$this->input->hasOption('stop-on-failure')
         || !$this->input->getOption('stop-on-failure'))
        {
            return;
        }
        
        if ($event->getResult() === ExampleEvent::FAILED
         || $event->getResult() === ExampleEvent::BROKEN)
        {
            throw new StopOnFailureException('Example failed');
        }
    }
}
