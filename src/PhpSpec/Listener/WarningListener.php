<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use PhpSpec\Console\IO;
use PhpSpec\Locator\ResourceManager;
use PhpSpec\CodeGenerator\GeneratorManager;

use PhpSpec\Event\SpecificationEvent;
use PhpSpec\Event\SuiteEvent;

class WarningListener implements EventSubscriberInterface
{
    /**
     * @var \PhpSpec\Console\IO
     */
    private $io;

    /**
     * @var string
     */
    private $warnings = array();

    public function __construct(IO $io)
    {
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            'afterSpecification'  => array('afterSpecification', -10),
            'afterSuite'          => array('afterSuite', -10),
        );
    }

    public function afterSpecification(SpecificationEvent $event)
    {
        $this->warnings = array_merge(
            $this->warnings,
            $event->getSpecification()->getWarnings()
        );
    }

    public function afterSuite(SuiteEvent $event)
    {
        foreach ($this->warnings as $warning) {
            $this->io->writeln('Warning: ' . $warning);
        }
    }
}
