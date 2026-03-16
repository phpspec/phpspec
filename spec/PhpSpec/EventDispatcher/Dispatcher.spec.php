<?php

use PhpSpec\EventDispatcher\Dispatcher;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event;
use PhpSpec\EventDispatcher\Subscriber;
use PhpSpec\EventDispatcher\Listener;

describe(Dispatcher::class, function() {

    it("dispatches events to subscribers", function() {
        $called = false;
        $d = new Dispatcher();

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

        $d->addSubscriber($subscriber);
        $d->dispatch($event, 'test.event');
        expect($called)->toBe(true);
    });

    it("dispatches events to listeners", function() {
        $called = false;
        $d = new Dispatcher();

        $event = new class implements Event {
            public function getName(): string { return 'test.listener'; }
        };

        $listener = new class($called) implements Listener {
            public function __construct(private &$called) {}
            public function listen(Event $event): void {
                $this->called = true;
            }
        };

        $d->addListener($listener);
        $d->dispatch($event, 'test.listener');
        expect($called)->toBe(true);
    });

    it("dispatches events to subscriber with array of methods", function() {
        $log = [];
        $d = new Dispatcher();

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

        $d->addSubscriber($subscriber);
        $d->dispatch($event, 'test.array');
        expect($log)->toBe(['first', 'second']);
    });

    it("removes a subscriber so it no longer receives events", function() {
        $called = 0;
        $d = new Dispatcher();

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

        $d->addSubscriber($subscriber);
        $d->dispatch($event, 'test.remove');
        expect($called)->toBe(1);

        $d->removeSubscriber($subscriber);
        $d->dispatch($event, 'test.remove');
        expect($called)->toBe(1);
    });

    it("handles removing a subscriber that was never added", function () {
        $d = new Dispatcher();

        $subscriber = new class implements \PhpSpec\EventDispatcher\Subscriber {
            public function getSubscribedEvents(): array {
                return ['never.registered' => 'onNever'];
            }
            public function onNever($event) {}
        };

        $d->removeSubscriber($subscriber);
        expect(true)->toBeTrue();
    });

    it("manages scope stack", function(\PhpSpec\Specification\ExampleRegistry $mockScope) {
        $d = new Dispatcher();
        expect($d->currentScope())->toBeNull();
        $d->pushScope($mockScope);
        expect($d->currentScope())->toBe($mockScope);
        $d->popScope();
        expect($d->currentScope())->toBeNull();
    });

    it("saves and restores state", function(\PhpSpec\Specification\ExampleRegistry $mockScope) {
        $d = new Dispatcher();
        $saved = $d->saveState();

        $d->pushScope($mockScope);
        expect($d->currentScope())->toBe($mockScope);

        $d->reset();
        expect($d->currentScope())->toBeNull();

        $d->restoreState($saved);
        expect($d->currentScope())->toBeNull();
    });

    it("is accessible via DispatcherRegistry", function() {
        $d = new Dispatcher();
        DispatcherRegistry::set($d);
        expect(DispatcherRegistry::get())->toBe($d);
    });

});
