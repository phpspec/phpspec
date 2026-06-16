<?php

use PhpSpec\Extensions\ListenerExtension;

describe(ListenerExtension::class, function () {

    let('listener', fn() => new class extends ListenerExtension {
        public array $log = [];

        public function beforeSuite(): void
        {
            $this->log[] = 'beforeSuite';
        }

        public function afterSuite(): void
        {
            $this->log[] = 'afterSuite';
        }

        public function afterExample(string $title, float $duration): void
        {
            $this->log[] = "afterExample:$title";
        }
    });

    it('has empty defaults for all methods', function () {
        $listener = new class extends ListenerExtension {};
        // None of these should throw
        $listener->beforeSuite();
        $listener->afterSuite();
        $listener->beforeSpec('spec');
        $listener->afterSpec('spec');
        $listener->beforeExample('test');
        $listener->afterExample('test', 0.1);
        $listener->onPass('test');
        $listener->onFail('test', 'msg');
        $listener->onError('test', 'msg');
        $listener->onPending('test');
        $listener->onSkipped('test');
        expect(true)->toBeTrue();
    });

    it('can be overridden selectively', function () {
        $this->listener->beforeSuite();
        $this->listener->afterExample('example 1', 0.5);
        $this->listener->afterSuite();

        expect($this->listener->log)->toBe(['beforeSuite', 'afterExample:example 1', 'afterSuite']);
    });
});
