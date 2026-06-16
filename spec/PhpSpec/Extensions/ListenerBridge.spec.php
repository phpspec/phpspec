<?php

use PhpSpec\EventDispatcher\Event\ExampleCompleted;
use PhpSpec\EventDispatcher\Event\ExampleStarted;
use PhpSpec\EventDispatcher\Event\SpecificationFinished;
use PhpSpec\EventDispatcher\Event\SpecificationStarted;
use PhpSpec\EventDispatcher\Event\SuiteFinished;
use PhpSpec\EventDispatcher\Event\SuiteStarted;
use PhpSpec\Extensions\ListenerBridge;
use PhpSpec\Extensions\ListenerExtension;
use PhpSpec\Result\ExampleResult;

describe(ListenerBridge::class, function () {

    let('log', fn() => []);

    let('listener', function () {
        return new class extends ListenerExtension {
            public array $log = [];

            public function beforeSuite(): void
            {
                $this->log[] = 'beforeSuite';
            }

            public function afterSuite(): void
            {
                $this->log[] = 'afterSuite';
            }

            public function beforeSpec(string $title): void
            {
                $this->log[] = "beforeSpec:$title";
            }

            public function afterSpec(string $title): void
            {
                $this->log[] = "afterSpec:$title";
            }

            public function beforeExample(string $title): void
            {
                $this->log[] = "beforeExample:$title";
            }

            public function afterExample(string $title, float $duration): void
            {
                $this->log[] = "afterExample:$title";
            }

            public function onPass(string $title): void
            {
                $this->log[] = "onPass:$title";
            }

            public function onFail(string $title, string $message): void
            {
                $this->log[] = "onFail:$title";
            }
        };
    });

    it('subscribes to the correct events', function () {
        $bridge = new ListenerBridge($this->listener);
        $events = $bridge->getSubscribedEvents();

        expect($events)->toHaveKey(SuiteStarted::NAME);
        expect($events)->toHaveKey(SuiteFinished::NAME);
        expect($events)->toHaveKey(SpecificationStarted::NAME);
        expect($events)->toHaveKey(SpecificationFinished::NAME);
        expect($events)->toHaveKey(ExampleStarted::NAME);
        expect($events)->toHaveKey(ExampleCompleted::NAME);
    });

    it('calls beforeSuite on suite started', function () {
        $bridge = new ListenerBridge($this->listener);
        $bridge->onSuiteStarted(new SuiteStarted());

        expect($this->listener->log)->toBe(['beforeSuite']);
    });

    it('calls afterSuite on suite finished', function () {
        $bridge = new ListenerBridge($this->listener);
        $bridge->onSuiteFinished(new SuiteFinished());

        expect($this->listener->log)->toBe(['afterSuite']);
    });

    it('calls beforeSpec on specification started', function () {
        $bridge = new ListenerBridge($this->listener);
        $bridge->onSpecStarted(new SpecificationStarted('/path/to/Foo.spec.php'));

        expect($this->listener->log)->toBe(['beforeSpec:/path/to/Foo.spec.php']);
    });

    it('calls afterSpec on specification finished', function () {
        $bridge = new ListenerBridge($this->listener);
        $bridge->onSpecFinished(new SpecificationFinished('Foo'));

        expect($this->listener->log)->toBe(['afterSpec:Foo']);
    });

    it('calls beforeExample on example started', function () {
        $bridge = new ListenerBridge($this->listener);
        $bridge->onExampleStarted(new ExampleStarted('works'));

        expect($this->listener->log)->toBe(['beforeExample:works']);
    });

    it('routes passing example to afterExample + onPass', function () {
        $bridge = new ListenerBridge($this->listener);
        $result = new ExampleResult('works', []);
        $bridge->onExampleCompleted(new ExampleCompleted('works', $result));

        expect($this->listener->log)->toContain('afterExample:works');
        expect($this->listener->log)->toContain('onPass:works');
    });

    it('routes failed example to afterExample + onFail', function () {
        $bridge = new ListenerBridge($this->listener);
        $result = new ExampleResult('fails', [\PhpSpec\Result\MatchResult::failed(1, 2, 'Expected 1 to be 2', __FILE__, __LINE__)]);
        $bridge->onExampleCompleted(new ExampleCompleted('fails', $result));

        expect($this->listener->log)->toContain('afterExample:fails');
        expect($this->listener->log)->toContain('onFail:fails');
    });

    it('routes errored example to afterExample + onError', function () {
        $bridge = new ListenerBridge($this->listener);
        $result = new ExampleResult('errors', [], isError: true);
        $result->setError(new \PhpSpec\Specification\ExampleError('boom', new \RuntimeException('boom')));
        $bridge->onExampleCompleted(new ExampleCompleted('errors', $result));

        expect($this->listener->log)->toContain('afterExample:errors');
    });

    it('routes pending example to afterExample + onPending', function () {
        $bridge = new ListenerBridge($this->listener);
        $result = new ExampleResult('pending', [], false, true);
        $bridge->onExampleCompleted(new ExampleCompleted('pending', $result));

        expect($this->listener->log)->toContain('afterExample:pending');
    });

    it('routes skipped example to afterExample + onSkipped', function () {
        $bridge = new ListenerBridge($this->listener);
        $result = new ExampleResult('skipped', [], false, false, true);
        $bridge->onExampleCompleted(new ExampleCompleted('skipped', $result));

        expect($this->listener->log)->toContain('afterExample:skipped');
    });
});
