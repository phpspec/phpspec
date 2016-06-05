<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\SpecificationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

interface SpecificationListener extends EventSubscriberInterface
{
    /**
     * @param SpecificationEvent $event
     */
    public function beforeSpecification(SpecificationEvent $event);

    /**
     * @param SpecificationEvent $event
     */
    public function afterSpecification(SpecificationEvent $event);
}
