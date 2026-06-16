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

namespace PhpSpec\EventDispatcher\Subscriber;

use PhpSpec\EventDispatcher\Event\ExampleErrored;
use PhpSpec\EventDispatcher\Event\ExampleRunned;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\EventDispatcher\Subscriber;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\ExampleResultRegistry;

/**
 * @internal
 * Wires example lifecycle events to collect match results and record example outcomes.
 *
 * Listens for match creation, example completion, and example error events to build
 * ExampleResult objects and register them with the result registry.
 */
final readonly class ExampleSubscriber implements Subscriber
{
    /**
     * @param ExampleResultRegistry $subscribed the registry that collects matches and example results
     */
    public function __construct(private ExampleResultRegistry $subscribed) {}

    /** {@inheritdoc} */
    public function getSubscribedEvents(): array
    {
        return [
            ExampleRunned::NAME => 'onExampleRunned',
            MatchCreated::NAME => 'onMatchCreated',
            ExampleErrored::NAME => 'onExampleErrored',
        ];
    }

    /**
     * Registers a deferred match closure when a match is created during an example.
     *
     * @param MatchCreated $event the match creation event carrying the closure
     */
    public function onMatchCreated(MatchCreated $event): void
    {
        $this->subscribed->addMatch($event->getMatch());
    }

    /**
     * Evaluates all deferred matches and records the example result when an example finishes.
     *
     * Skips match evaluation if the example already errored. Resets matches after collection.
     *
     * @param ExampleRunned $event the example completion event
     */
    public function onExampleRunned(ExampleRunned $event): void
    {
        if ($this->subscribed->isError()) {
            $this->subscribed->resetMatches();
            return;
        }

        $matchResults = [];

        foreach ($this->subscribed->getMatches() as $match) {
            $matchResults[] = $match();
        }

        $this->subscribed->addExampleResult(new ExampleResult($event->getTitle(), $matchResults));
        $this->subscribed->resetMatches();
    }

    /**
     * Records an errored example result and stores the error on the registry.
     *
     * @param ExampleErrored $event the error event carrying the example title and error details
     */
    public function onExampleErrored(ExampleErrored $event): void
    {
        $this->subscribed->addExampleResult(new ExampleResult(
            title: $event->getTitle(),
            matchResults: [],
            isError: true,
        ));
        $this->subscribed->setError($event->getError());
    }
}
