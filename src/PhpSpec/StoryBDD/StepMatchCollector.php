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

namespace PhpSpec\StoryBDD;

use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\EventDispatcher\Subscriber;

/**
 * Subscribes to MatchCreated events to collect expectations made during step execution.
 * After step completion, evaluates all collected matches and returns their results.
 */
final class StepMatchCollector implements Subscriber
{
    /** @var callable[] deferred match evaluators collected from events */
    private array $matches = [];

    /**
     * Returns the event names this subscriber listens to.
     *
     * @return array<string, string> map of event name to handler method name
     */
    public function getSubscribedEvents(): array
    {
        return [
            MatchCreated::NAME => 'onMatchCreated',
        ];
    }

    /**
     * Handles a MatchCreated event by storing the match for later evaluation.
     *
     * @param MatchCreated $event the event containing the deferred match
     * @return void
     */
    public function onMatchCreated(MatchCreated $event): void
    {
        $this->matches[] = $event->getMatch();
    }

    /**
     * Evaluates all collected matches and clears the internal collection.
     *
     * @return MatchResult[] the results of evaluating each collected match
     */
    public function evaluateMatches(): array
    {
        $results = [];
        foreach ($this->matches as $match) {
            $results[] = $match();
        }
        $this->matches = [];
        return $results;
    }
}
