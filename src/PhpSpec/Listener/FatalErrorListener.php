<?php

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FatalErrorListener implements EventSubscriberInterface {

    public static function getSubscribedEvents() {
        return array(
            'beforeExample' => array('beforeExample', -20),
            'afterExample'  => array('afterExample', -20),
            'afterSuite'    => array('afterSuite', -20),
        );
    }

    public function beforeExample()
    {
        // update and create a state
    }

    public function afterExample()
    {
        // update state to finished
    }

    public function afterSuite()
    {
        // update suite to finished
    }
}
