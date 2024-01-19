<?php

namespace PhpSpec\Listener;

use PhpSpec\Console\ConsoleIO;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class BootstrapListener implements EventSubscriberInterface
{
    public function __construct(
        private ConsoleIO $io
    )
    {
    }

    public static function getSubscribedEvents() : array
    {
        return array('beforeSuite' => array('beforeSuite', 1100));
    }

    public function beforeSuite(): void
    {
        if ($bootstrap = $this->io->getBootstrapPath()) {
            if (!is_file($bootstrap)) {
                throw new \RuntimeException(sprintf("Bootstrap file '%s' does not exist", $bootstrap));
            }

            require $bootstrap;
        }
    }
}
