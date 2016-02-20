<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\SpecificationEvent;

interface SpecificationListener
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
