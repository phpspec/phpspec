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
 * @internal Not part of the public API. Use DSL functions instead.
 *
 * Instance-based event bus that coordinates DSL functions, spec tree building, and result collection.
 *
 * Manages a scope stack to track the current context/example registry during spec
 * file loading, and dispatches events to registered subscribers and listeners.
 *
 * Accessed via {@see DispatcherRegistry::dispatcher()}.
 */
final class Dispatcher
{
    /** @var Listener[] Registered listeners that receive all events */
    private array $listeners = [];

    /** @var Subscriber[] Registered subscribers that receive specific named events */
    private array $subscribers = [];

    /** @var array<string, array<int, array{subscriber: Subscriber, methods: string|string[]}>> Pre-indexed event→subscriber map */
    private array $subscribersByEvent = [];

    /** @var ExampleRegistry[] Stack tracking the current scope during spec loading */
    private array $scopeStack = [];

    /**
     * Dispatches an event to all matching subscribers and all listeners.
     *
     * @param Event $event the event instance to dispatch
     * @param string $eventName the event name to match against subscriber registrations
     */
    public function dispatch(Event $event, string $eventName): void
    {
        if (isset($this->subscribersByEvent[$eventName])) {
            foreach ($this->subscribersByEvent[$eventName] as $entry) {
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

        foreach ($this->listeners as $listener) {
            $listener->listen($event);
        }
    }

    /**
     * Registers a listener that receives all dispatched events.
     *
     * @param Listener $listener the listener to add
     */
    public function addListener(Listener $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Registers a subscriber that receives specific named events.
     *
     * @param Subscriber $subscriber the subscriber to add
     */
    public function addSubscriber(Subscriber $subscriber): void
    {
        $this->subscribers[] = $subscriber;

        foreach ($subscriber->getSubscribedEvents() as $eventName => $methods) {
            $this->subscribersByEvent[$eventName][] = [
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
    public function removeSubscriber(Subscriber $subscriber): void
    {
        $key = array_search($subscriber, $this->subscribers, true);
        if ($key !== false) {
            array_splice($this->subscribers, $key, 1);
        }

        foreach ($subscriber->getSubscribedEvents() as $eventName => $methods) {
            if (!isset($this->subscribersByEvent[$eventName])) {
                continue;
            }
            foreach ($this->subscribersByEvent[$eventName] as $i => $entry) {
                if ($entry['subscriber'] === $subscriber) {
                    array_splice($this->subscribersByEvent[$eventName], $i, 1);
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
    public function pushScope(ExampleRegistry $registry): void
    {
        $this->scopeStack[] = $registry;
    }

    /**
     * Removes the topmost registry from the scope stack.
     */
    public function popScope(): void
    {
        array_pop($this->scopeStack);
    }

    /**
     * Returns the current active scope registry, or null if the stack is empty.
     */
    public function currentScope(): ?ExampleRegistry
    {
        return end($this->scopeStack) ?: null;
    }
}
