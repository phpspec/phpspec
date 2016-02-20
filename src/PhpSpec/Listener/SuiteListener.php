<?php

namespace PhpSpec\Listener;

use PhpSpec\Event\SuiteEvent;

interface SuiteListener
{
    /**
     * @param SuiteEvent $suiteEvent
     */
    public function beforeSuite(SuiteEvent $suiteEvent);

    /**
     * @param SuiteEvent $suiteEvent
     */
    public function afterSuite(SuiteEvent $suiteEvent);
}
