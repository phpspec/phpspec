<?php

use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\EventDispatcher\Event;
use PhpSpec\EventDispatcher\Subscriber;
use PhpSpec\EventDispatcher\Listener;

describe(Dispatcher::class, function() {

    it("dispatches events to subscribers", function() {
        $called = false;

        $event = new class implements Event {
            public function getName(): string { return 'test.event'; }
        };

        $subscriber = new class($called) implements Subscriber {
            public function __construct(private &$called) {}
            public function getSubscribedEvents(): array {
                return ['test.event' => 'onTest'];
            }
            public function onTest($event) {
                $this->called = true;
            }
        };

        Dispatcher::addSubscriber($subscriber);
        Dispatcher::dispatch($event, 'test.event');
        expect($called)->toBe(true);
    });

    it("dispatches events to listeners", function() {
        $called = false;

        $event = new class implements Event {
            public function getName(): string { return 'test.listener'; }
        };

        $listener = new class($called) implements Listener {
            public function __construct(private &$called) {}
            public function listen(Event $event): void {
                $this->called = true;
            }
        };

        Dispatcher::addListener($listener);
        Dispatcher::dispatch($event, 'test.listener');
        expect($called)->toBe(true);
    });

    it("dispatches events to subscriber with array of methods", function() {
        $log = [];

        $event = new class implements Event {
            public function getName(): string { return 'test.array'; }
        };

        $subscriber = new class($log) implements Subscriber {
            public function __construct(private &$log) {}
            public function getSubscribedEvents(): array {
                return ['test.array' => ['onFirst', 'onSecond']];
            }
            public function onFirst($event) {
                $this->log[] = 'first';
            }
            public function onSecond($event) {
                $this->log[] = 'second';
            }
        };

        Dispatcher::addSubscriber($subscriber);
        Dispatcher::dispatch($event, 'test.array');
        expect($log)->toBe(['first', 'second']);
    });

    it("removes a subscriber so it no longer receives events", function() {
        $called = 0;

        $event = new class implements Event {
            public function getName(): string { return 'test.remove'; }
        };

        $subscriber = new class($called) implements Subscriber {
            public function __construct(private &$called) {}
            public function getSubscribedEvents(): array {
                return ['test.remove' => 'onTest'];
            }
            public function onTest($event) {
                $this->called++;
            }
        };

        Dispatcher::addSubscriber($subscriber);
        Dispatcher::dispatch($event, 'test.remove');
        expect($called)->toBe(1);

        Dispatcher::removeSubscriber($subscriber);
        Dispatcher::dispatch($event, 'test.remove');
        expect($called)->toBe(1);
    });

    it("handles removing a subscriber that was never added", function () {
        $subscriber = new class implements \PhpSpec\EventDispatcher\Subscriber {
            public function getSubscribedEvents(): array {
                return ['never.registered' => 'onNever'];
            }
            public function onNever($event) {}
        };

        Dispatcher::removeSubscriber($subscriber);
        expect(true)->toBeTrue();
    });

    it("manages scope stack", function(\PhpSpec\Specification\ExampleRegistry $mockScope) {
        $before = Dispatcher::currentScope();
        Dispatcher::pushScope($mockScope);
        expect(Dispatcher::currentScope())->toBe($mockScope);
        Dispatcher::popScope();
        expect(Dispatcher::currentScope())->toBe($before);
    });

    it("saves and restores state", function(\PhpSpec\Specification\ExampleRegistry $mockScope) {
        $saved = Dispatcher::saveState();

        Dispatcher::pushScope($mockScope);
        expect(Dispatcher::currentScope())->toBe($mockScope);

        Dispatcher::reset();
        expect(Dispatcher::currentScope())->toBeNull();

        Dispatcher::restoreState($saved);
        expect(Dispatcher::currentScope())->not()->toBe($mockScope);
    });

});
