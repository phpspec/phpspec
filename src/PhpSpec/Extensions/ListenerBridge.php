<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 * (c) Ciaran McNulty <ciaran@ciaranmcnulty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Extensions;

use PhpSpec\EventDispatcher\Event;
use PhpSpec\EventDispatcher\Event\ExampleCompleted;
use PhpSpec\EventDispatcher\Event\ExampleStarted;
use PhpSpec\EventDispatcher\Event\SpecificationFinished;
use PhpSpec\EventDispatcher\Event\SpecificationStarted;
use PhpSpec\EventDispatcher\Event\SuiteFinished;
use PhpSpec\EventDispatcher\Event\SuiteStarted;
use PhpSpec\EventDispatcher\Subscriber;

/**
 * Adapts a ListenerExtension into a Subscriber, translating internal events
 * to simplified callback methods.
 */
final class ListenerBridge implements Subscriber
{
    /**
     * @param ListenerExtension $extension The listener extension to bridge into the event system
     */
    public function __construct(private readonly ListenerExtension $extension) {}

    /**
     * Returns the map of event names to handler method names on this bridge.
     *
     * @return array<string, string>
     */
    public function getSubscribedEvents(): array
    {
        return [
            SuiteStarted::NAME => 'onSuiteStarted',
            SuiteFinished::NAME => 'onSuiteFinished',
            SpecificationStarted::NAME => 'onSpecStarted',
            SpecificationFinished::NAME => 'onSpecFinished',
            ExampleStarted::NAME => 'onExampleStarted',
            ExampleCompleted::NAME => 'onExampleCompleted',
        ];
    }

    /**
     * Delegates the suite started event to the extension's beforeSuite callback.
     *
     * @param Event $event The suite started event
     *
     * @return void
     */
    public function onSuiteStarted(Event $event): void
    {
        $this->extension->beforeSuite();
    }

    /**
     * Delegates the suite finished event to the extension's afterSuite callback.
     *
     * @param Event $event The suite finished event
     *
     * @return void
     */
    public function onSuiteFinished(Event $event): void
    {
        $this->extension->afterSuite();
    }

    /**
     * Delegates the specification started event to the extension's beforeSpec callback.
     *
     * @param SpecificationStarted $event The specification started event containing the path
     *
     * @return void
     */
    public function onSpecStarted(SpecificationStarted $event): void
    {
        $this->extension->beforeSpec($event->getPath());
    }

    /**
     * Delegates the specification finished event to the extension's afterSpec callback.
     *
     * @param SpecificationFinished $event The specification finished event containing the title
     *
     * @return void
     */
    public function onSpecFinished(SpecificationFinished $event): void
    {
        $this->extension->afterSpec($event->getTitle());
    }

    /**
     * Delegates the example started event to the extension's beforeExample callback.
     *
     * @param ExampleStarted $event The example started event containing the title
     *
     * @return void
     */
    public function onExampleStarted(ExampleStarted $event): void
    {
        $this->extension->beforeExample($event->getTitle());
    }

    /**
     * Delegates the example completed event to the appropriate extension callbacks
     * (afterExample, then onPass/onFail/onError/onPending/onSkipped based on result).
     *
     * @param ExampleCompleted $event The example completed event containing the result
     *
     * @return void
     */
    public function onExampleCompleted(ExampleCompleted $event): void
    {
        $result = $event->getResult();
        $title = $event->getTitle();
        $duration = $result->getDuration();

        $this->extension->afterExample($title, $duration);

        if ($result->isPending() || $result->isSkipped()) {
            if ($result->isSkipped()) {
                $this->extension->onSkipped($title);
            } else {
                $this->extension->onPending($title);
            }
        } elseif ($result->isError()) {
            $this->extension->onError($title, $result->getMessage());
        } elseif ($result->isFailure()) {
            $this->extension->onFail($title, $result->getMessage());
        } else {
            $this->extension->onPass($title);
        }
    }
}
