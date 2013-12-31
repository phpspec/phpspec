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

use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\StopOnFailureException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class StopOnFailureListener
 * @package PhpSpec\Listener
 */
class StopOnFailureListener implements EventSubscriberInterface
{
    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private $input;

    /**
     * @param InputInterface $input
     */
    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'afterExample' => array('afterExample', -100),
        );
    }

    /**
     * @param ExampleEvent $event
     *
     * @throws \PhpSpec\Exception\Example\StopOnFailureException
     */
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
