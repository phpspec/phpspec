<?php

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Event\ExampleEvent;

class StatisticsCollector implements EventSubscriberInterface
{
    private $globalResult  = 0;
    private $passedEvents  = array();
    private $pendingEvents = array();
    private $failedEvents  = array();
    private $brokenEvents  = array();

    public static function getSubscribedEvents()
    {
        return array('afterExample' => array('afterExample', 10));
    }

    public function afterExample(ExampleEvent $event)
    {
        $this->globalResult = max($this->globalResult, $event->getResult());

        switch ($event->getResult()) {
            case ExampleEvent::PASSED:
                $this->passedEvents[] = $event;
                break;
            case ExampleEvent::PENDING:
                $this->pendingEvents[] = $event;
                break;
            case ExampleEvent::FAILED:
                $this->failedEvents[] = $event;
                break;
            case ExampleEvent::BROKEN:
                $this->brokenEvents[] = $event;
                break;
        }
    }

    public function getGlobalResult()
    {
        return $this->globalResult;
    }

    public function getAllEvents()
    {
        return array_merge(
            $this->passedEvents,
            $this->pendingEvents,
            $this->failedEvents,
            $this->brokenEvents
        );
    }

    public function getPassedEvents()
    {
        return $this->passedEvents;
    }

    public function getPendingEvents()
    {
        return $this->pendingEvents;
    }

    public function getFailedEvents()
    {
        return $this->failedEvents;
    }

    public function getBrokenEvents()
    {
        return $this->brokenEvents;
    }

    public function getCountsHash()
    {
        return array(
            'passed'  => count($this->getPassedEvents()),
            'pending' => count($this->getPendingEvents()),
            'failed'  => count($this->getFailedEvents()),
            'broken'  => count($this->getBrokenEvents()),
        );
    }

    public function getEventsCount()
    {
        return count($this->getAllEvents());
    }
}
