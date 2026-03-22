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

namespace PhpSpec\EventDispatcher;

use PhpSpec\Specification\ExampleRegistry;

/**
 * Static event bus that coordinates DSL functions, spec tree building, and result collection.
 *
 * Manages a scope stack to track the current context/example registry during spec
 * file loading, and dispatches events to registered subscribers and listeners.
 */
final class Dispatcher
{
    /** @var Event[] Dispatched events log */
    private static array $events = [];

    /** @var Listener[] Registered listeners that receive all events */
    private static array $listeners = [];

    /** @var Subscriber[] Registered subscribers that receive specific named events */
    private static array $subscribers = [];

    /** @var array<string, array<int, array{subscriber: Subscriber, methods: string|string[]}>> Pre-indexed event→subscriber map */
    private static array $subscribersByEvent = [];

    /** @var ExampleRegistry[] Stack tracking the current scope during spec loading */
    private static array $scopeStack = [];

    /**
     * Dispatches an event to all matching subscribers and all listeners.
     *
     * @param Event $event the event instance to dispatch
     * @param string $eventName the event name to match against subscriber registrations
     */
    public static function dispatch(Event $event, string $eventName): void
    {
        if (isset(self::$subscribersByEvent[$eventName])) {
            foreach (self::$subscribersByEvent[$eventName] as $entry) {
                $methods = $entry['methods'];
                $subscriber = $entry['subscriber'];
                if (is_array($methods)) {
                    foreach ($methods as $method) {
                        $subscriber->$method($event);
                    }
                } elseif (is_string($methods)) {
                    $subscriber->$methods($event);
                }
            }
        }

        foreach (self::$listeners as $listener) {
            $listener->listen($event);
        }
    }

    /**
     * Registers a listener that receives all dispatched events.
     *
     * @param Listener $listener the listener to add
     */
    public static function addListener(Listener $listener): void
    {
        self::$listeners[] = $listener;
    }

    /**
     * Registers a subscriber that receives specific named events.
     *
     * @param Subscriber $subscriber the subscriber to add
     */
    public static function addSubscriber(Subscriber $subscriber): void
    {
        self::$subscribers[] = $subscriber;

        foreach ($subscriber->getSubscribedEvents() as $eventName => $methods) {
            self::$subscribersByEvent[$eventName][] = [
                'subscriber' => $subscriber,
                'methods' => $methods,
            ];
        }
    }

    /**
     * Removes a subscriber from the dispatch pool.
     *
     * @param Subscriber $subscriber the subscriber to remove
     */
    public static function removeSubscriber(Subscriber $subscriber): void
    {
        $key = array_search($subscriber, self::$subscribers, true);
        if ($key !== false) {
            array_splice(self::$subscribers, $key, 1);
        }

        foreach ($subscriber->getSubscribedEvents() as $eventName => $methods) {
            if (!isset(self::$subscribersByEvent[$eventName])) {
                continue;
            }
            foreach (self::$subscribersByEvent[$eventName] as $i => $entry) {
                if ($entry['subscriber'] === $subscriber) {
                    array_splice(self::$subscribersByEvent[$eventName], $i, 1);
                    break;
                }
            }
        }
    }

    /**
     * Pushes a registry onto the scope stack, making it the current scope.
     *
     * @param ExampleRegistry $registry the registry to become the active scope
     */
    public static function pushScope(ExampleRegistry $registry): void
    {
        self::$scopeStack[] = $registry;
    }

    /**
     * Removes the topmost registry from the scope stack.
     */
    public static function popScope(): void
    {
        array_pop(self::$scopeStack);
    }

    /**
     * Returns the current active scope registry, or null if the stack is empty.
     */
    public static function currentScope(): ?ExampleRegistry
    {
        return end(self::$scopeStack) ?: null;
    }

    /**
     * Captures all static state so it can be restored after an in-process phpspec run.
     *
     * @return array{events: Event[], listeners: Listener[], subscribers: Subscriber[], subscribersByEvent: array, scopeStack: ExampleRegistry[]}
     */
    public static function saveState(): array
    {
        return [
            'events' => self::$events,
            'listeners' => self::$listeners,
            'subscribers' => self::$subscribers,
            'subscribersByEvent' => self::$subscribersByEvent,
            'scopeStack' => self::$scopeStack,
        ];
    }

    /**
     * Restores static state previously captured by saveState().
     *
     * @param array{events: Event[], listeners: Listener[], subscribers: Subscriber[], subscribersByEvent: array, scopeStack: ExampleRegistry[]} $state
     */
    public static function restoreState(array $state): void
    {
        self::$events = $state['events'];
        self::$listeners = $state['listeners'];
        self::$subscribers = $state['subscribers'];
        self::$subscribersByEvent = $state['subscribersByEvent'];
        self::$scopeStack = $state['scopeStack'];
    }

    /**
     * Resets all static state to empty. Used before an in-process phpspec run.
     */
    public static function reset(): void
    {
        self::$events = [];
        self::$listeners = [];
        self::$subscribers = [];
        self::$subscribersByEvent = [];
        self::$scopeStack = [];
    }
}
