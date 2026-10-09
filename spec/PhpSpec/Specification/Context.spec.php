<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\FilterRegistry;
use PhpSpec\LineTargetRegistry;
use PhpSpec\Specification\Context;
use PhpSpec\Specification\Subject;
use PhpSpec\Result\ContextResult;
use PhpSpec\StopConditions;
use PhpSpec\StopRegistry;
use PhpSpec\TitleFilter;

interface ContextSpecMailer
{
    public function send(string $message): void;
}

describe(Context::class, function() {

    it("instantiates", function() {
        $ctx = new Context("test context", function() {});
        expect($ctx)->toBeAnInstanceOf(Context::class);
    });

    it("runs its closure and returns a ContextResult", function() {
        $ctx = new Context("test context", function() {});
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($result)->toBeAnInstanceOf(ContextResult::class);
        expect($result->getTitle())->toBe("test context");
    });

    it("collects nested examples", function() {
        $ran = false;
        $ctx = new Context("test context", function() use (&$ran) {
            it("runs", function() use (&$ran) {
                $ran = true;
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($ran)->toBe(true);
        expect($result->getResults())->toHaveCount(1);
    });

    it("catches errors in closure", function() {
        $ctx = new Context("broken", function() {
            throw new \RuntimeException("boom");
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($result->isError())->toBe(true);
    });

    it("fails each example that reads a let whose binding throws, and leaves one that never reads it alone", function() {
        $ctx = new Context("broken let", function() {
            let('money', fn() => throw new \RuntimeException('not yet'));
            it("one", function() { expect($this->money)->toBeNull(); });
            it("two", function() { expect($this->money)->toBeNull(); });
            it("three", function() { expect(true)->toBeTrue(); });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);

        $original = \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher();
        \PhpSpec\EventDispatcher\DispatcherRegistry::set(new \PhpSpec\EventDispatcher\Dispatcher());
        try {
            $results = $ctx->run()->getResults();
        } finally {
            \PhpSpec\EventDispatcher\DispatcherRegistry::set($original);
        }

        expect($results)->toHaveCount(3);
        expect($results[0]->isError())->toBeTrue();
        expect($results[0]->getMessage())->toBe('not yet');
        expect($results[0]->getTitle())->toBe('one');
        expect($results[1]->isError())->toBeTrue();
        expect($results[2]->isError())->toBeFalse();
    });

    it("fails the example whose beforeEach threw, and goes on to the next", function() {
        $ctx = new Context("broken hook", function() {
            beforeEach(function() { throw new \RuntimeException('no setup'); });
            it("one", function() { expect(true)->toBeTrue(); });
            it("two", function() { expect(true)->toBeTrue(); });
        });
        $ctx->setWorld(new Subject());

        $results = $ctx->run()->getResults();

        expect($results)->toHaveCount(2);
        expect($results[1]->isError())->toBeTrue();
        expect($results[1]->getMessage())->toBe('no setup');
    });

    it("fails the example whose afterEach threw, and goes on to the next", function() {
        $ctx = new Context("broken teardown", function() {
            afterEach(function() { throw new \RuntimeException('no teardown'); });
            it("one", function() { expect(true)->toBeTrue(); });
            it("two", function() { expect(true)->toBeTrue(); });
        });
        $ctx->setWorld(new Subject());

        $results = $ctx->run()->getResults();

        expect($results)->toHaveCount(2);
        expect($results[0]->isError())->toBeTrue();
        expect($results[0]->getMessage())->toBe('no teardown');
        expect($results[0]->getTitle())->toBe('one');
    });

    it("keeps what the hooks printed with the example's output, around what the body printed", function() {
        $ctx = new Context("printing hooks", function() {
            beforeEach(function() { echo "setup "; });
            afterEach(function() { echo " teardown"; });
            it("prints", function() { echo "body"; });
        });
        $ctx->setWorld(new Subject());

        $results = $ctx->run()->getResults();

        expect($results[0]->getOutput())->toBe("setup body teardown");
    });

    it("exposes a mock injected into a let on the world, under the parameter's name, for the example's duration", function() {
        $world = new Subject();
        $seen = null;
        $ctx = new Context("injected let", function() use ($world, &$seen) {
            let('notifier', fn (ContextSpecMailer $mailer) => new \stdClass());
            it("reads the mailer", function() use ($world, &$seen) { $seen = $world->mailer; });
        });
        $ctx->setWorld($world);

        $ctx->run();

        expect($seen)->toBeAnInstanceOf(ContextSpecMailer::class);
        expect(isset($world->mailer))->toBeFalse();
    });

    it("hands each example a mock of its own, never the previous example's", function() {
        $seen = [];
        $ctx = new Context("fresh mocks", function() use (&$seen) {
            let('notifier', fn (ContextSpecMailer $mailer) => new \stdClass());
            beforeEach(function (ContextSpecMailer $mailer) use (&$seen) { $seen[] = $mailer; });
            it("one", function() {});
            it("two", function() {});
        });
        $ctx->setWorld(new Subject());

        $ctx->run();

        expect($seen)->toHaveCount(2);
        expect($seen[0])->not()->toBe($seen[1]);
    });

    it("runs beforeEach hooks before each example", function() {
        $log = [];
        $ctx = new Context("hooks test", function() use (&$log) {
            beforeEach(function() use (&$log) {
                $log[] = 'before';
            });
            it("first", function() use (&$log) {
                $log[] = 'ex1';
            });
            it("second", function() use (&$log) {
                $log[] = 'ex2';
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toBe(['before', 'ex1', 'before', 'ex2']);
    });

    it("runs afterEach hooks after each example", function() {
        $log = [];
        $ctx = new Context("hooks test", function() use (&$log) {
            afterEach(function() use (&$log) {
                $log[] = 'after';
            });
            it("first", function() use (&$log) {
                $log[] = 'ex1';
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toBe(['ex1', 'after']);
    });

    it("marks all child examples as pending when context is pending", function() {
        $ran = false;
        $ctx = new Context("pending context", function() use (&$ran) {
            it("should not run", function() use (&$ran) {
                $ran = true;
            });
        });
        $ctx->setPending(true);
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($ran)->toBe(false);
        expect($result->getResults())->toHaveCount(1);
        expect($result->getResults()[0]->isPending())->toBe(true);
    });

    it("skips hooks for pending examples", function() {
        $log = [];
        $ctx = new Context("pending hooks test", function() use (&$log) {
            beforeEach(function() use (&$log) {
                $log[] = 'before';
            });
            afterEach(function() use (&$log) {
                $log[] = 'after';
            });
            it("should not run hooks", function() use (&$log) {
                $log[] = 'ex1';
            });
        });
        $ctx->setPending(true);
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toBe([]);
    });

    it("collects xit as pending example", function() {
        $ran = false;
        $ctx = new Context("xit test", function() use (&$ran) {
            xit("pending example", function() use (&$ran) {
                $ran = true;
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($ran)->toBe(false);
        expect($result->getResults())->toHaveCount(1);
        expect($result->getResults()[0]->isPending())->toBe(true);
    });

    it("collects xdescribe as pending context", function() {
        $ran = false;
        $ctx = new Context("xdescribe test", function() use (&$ran) {
            xdescribe("pending context", function() use (&$ran) {
                it("inner example", function() use (&$ran) {
                    $ran = true;
                });
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($ran)->toBe(false);
    });

    it("runs only focused examples when fit is used", function() {
        $log = [];
        $ctx = new Context("focus test", function() use (&$log) {
            it("unfocused", function() use (&$log) {
                $log[] = 'unfocused';
            });
            fit("focused", function() use (&$log) {
                $log[] = 'focused';
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($log)->toBe(['focused']);
        expect($result->getResults())->toHaveCount(2);
        expect($result->getResults()[0]->isPending())->toBe(true);
        // Left out by the focus, and said so: the run can then warn, and a
        // reader does not take the sibling for work deferred on purpose.
        expect($result->getResults()[0]->isLeftOutByFocus())->toBe(true);
        expect($result->getResults()[0]->getReason())->toBe('left out by focus');
        expect($result->getResults()[1]->isLeftOutByFocus())->toBe(false);
    });

    it("runs beforeAll once before all examples", function() {
        $log = [];
        $ctx = new Context("beforeAll test", function() use (&$log) {
            beforeAll(function() use (&$log) {
                $log[] = 'beforeAll';
            });
            it("first", function() use (&$log) {
                $log[] = 'ex1';
            });
            it("second", function() use (&$log) {
                $log[] = 'ex2';
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toBe(['beforeAll', 'ex1', 'ex2']);
    });

    it("runs afterAll once after all examples", function() {
        $log = [];
        $ctx = new Context("afterAll test", function() use (&$log) {
            afterAll(function() use (&$log) {
                $log[] = 'afterAll';
            });
            it("first", function() use (&$log) {
                $log[] = 'ex1';
            });
            it("second", function() use (&$log) {
                $log[] = 'ex2';
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toBe(['ex1', 'ex2', 'afterAll']);
    });

    it("runs all children inside fdescribe", function() {
        $log = [];
        $ctx = new Context("fdescribe test", function() use (&$log) {
            it("unfocused sibling", function() use (&$log) {
                $log[] = 'sibling';
            });
            fdescribe("focused context", function() use (&$log) {
                it("child 1", function() use (&$log) {
                    $log[] = 'child1';
                });
                it("child 2", function() use (&$log) {
                    $log[] = 'child2';
                });
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($log)->toContain('child1');
        expect($log)->toContain('child2');
    });

    it("inherits parent hooks in nested contexts", function() {
        $log = [];
        $ctx = new Context("parent", function() use (&$log) {
            beforeEach(function() use (&$log) {
                $log[] = 'parent-before';
            });
            afterEach(function() use (&$log) {
                $log[] = 'parent-after';
            });
            context("child", function() use (&$log) {
                it("nested example", function() use (&$log) {
                    $log[] = 'nested';
                });
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toContain('parent-before');
        expect($log)->toContain('nested');
        expect($log)->toContain('parent-after');
    });

    it("uses let to modify world properties", function() {
        $ctx = new Context("let test", function() {
            let("calculator", fn() => new \stdClass());
            it("has the let value", function() {
                expect($this->calculator)->toBeAnInstanceOf(\stdClass::class);
            });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);
        $result = $ctx->run();
        expect($result->isError())->toBe(false);
        expect($result->getResults()[0]->isFailure())->toBe(false);
    });

    it("uses its function as alias for it", function() {
        $ran = false;
        $ctx = new Context("its test", function() use (&$ran) {
            its("works via its", function() use (&$ran) {
                $ran = true;
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($ran)->toBe(true);
    });

    it("uses xcontext as alias for xdescribe", function() {
        $ran = false;
        $ctx = new Context("xcontext test", function() use (&$ran) {
            xcontext("skipped", function() use (&$ran) {
                it("should not run", function() use (&$ran) {
                    $ran = true;
                });
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($ran)->toBe(false);
    });

    it("uses fcontext as alias for fdescribe", function() {
        $log = [];
        $ctx = new Context("fcontext test", function() use (&$log) {
            it("unfocused", function() use (&$log) {
                $log[] = 'unfocused';
            });
            fcontext("focused context", function() use (&$log) {
                it("focused child", function() use (&$log) {
                    $log[] = 'focused';
                });
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toContain('focused');
    });

    it("tracks error state", function() {
        $ctx = new Context("error test", function() {});
        expect($ctx->isError())->toBe(false);
        $ctx->setError(new \PhpSpec\Specification\ExampleError("err", new \RuntimeException("err")));
        expect($ctx->isError())->toBe(true);
    });

    it("tracks focused state", function() {
        $ctx = new Context("focus test", function() {});
        expect($ctx->isFocused())->toBe(false);
        $ctx->setFocused(true);
        expect($ctx->isFocused())->toBe(true);
    });

    it("pending function throws PendingException", function() {
        $ctx = new Context("pending test", function() {
            it("is pending", function() {
                pending("Not yet implemented");
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($result->getResults())->toHaveCount(1);
        expect($result->getResults()[0]->isPending())->toBe(true);
    });

    it("skip function throws SkippedException", function() {
        $ctx = new Context("skip test", function() {
            it("is skipped", function() {
                skip("Skipped");
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($result->getResults())->toHaveCount(1);
        expect($result->getResults()[0]->isSkipped())->toBe(true);
    });

    it("nested contexts inherit let bindings", function() {
        $ctx = new Context("let inheritance", function() {
            let("value", fn() => 42);
            context("nested", function() {
                it("has the let value", function() {
                    expect($this->value)->toBe(42);
                });
            });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);
        $result = $ctx->run();
        // The nested context should have 1 child result
        $nestedCtx = $result->getResults()[0];
        expect($nestedCtx->getResults())->toHaveCount(1);
    });

    it("builds a let when an example first reads it, once for that example, and afresh for the next", function() {
        $built = 0;
        $seen = [];
        $ctx = new Context("lazy let", function() use (&$built, &$seen) {
            let("val", function() use (&$built) {
                return ++$built;
            });
            it("reads twice", function() use (&$seen) {
                $seen[] = $this->val;
                $seen[] = $this->val;
            });
            it("reads once more", function() use (&$seen) {
                $seen[] = $this->val;
            });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);

        $ctx->run();

        expect($seen)->toBe([1, 1, 2]);
        expect($built)->toBe(2);
    });

    it("leaves a let nobody reads unbuilt", function() {
        $built = 0;
        $ctx = new Context("unread let", function() use (&$built) {
            let("val", function() use (&$built) {
                return ++$built;
            });
            it("looks elsewhere", function() {
                expect(true)->toBeTrue();
            });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);

        $ctx->run();

        expect($built)->toBe(0);
    });

    it("lets a hook arrange what a let consumes, the let being built after the hooks ran", function() {
        $ctx = new Context("hook before let", function() {
            let("answer", fn() => $this->base + 2);
            beforeEach(function() {
                $this->base = 40;
            });
            it("adds up", function() {
                expect($this->answer)->toBe(42);
            });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);

        $results = $ctx->run()->getResults();

        expect($results[0]->isError())->toBeFalse();
        expect($results[0]->isFailure())->toBeFalse();
    });

    it("re-evaluates let bindings for each example", function() {
        $counter = 0;
        $ctx = new Context("let re-eval", function() use (&$counter) {
            let("val", function() use (&$counter) {
                $counter++;
                return $counter;
            });
            it("first", function() {
                expect($this->val)->toBeOfType('int');
            });
            it("second", function() {
                expect($this->val)->toBeOfType('int');
            });
        });
        $world = new Subject();
        $ctx = $ctx->withWorld($world);
        $ctx->setWorld($world);
        $ctx->run();
        expect($counter)->toBe(2);
    });

    it("addMatcher registers custom matchers", function() {
        $ctx = new Context("custom matcher", function() {
            it("uses custom matcher", function() {
                addMatcher('toBeEven', fn($v) => $v % 2 === 0, 'Expected %s to be even');
                expect(4)->toBeEven();
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($result->isError())->toBe(false);
    });

    it("custom matcher with closure message", function() {
        $ctx = new Context("custom matcher closure msg", function() {
            it("works", function() {
                \PhpSpec\Specification\Expectation::addMatcher('toBeMultipleOf', fn($v, $d) => $v % $d === 0, fn($v, $d) => "Expected $v to be multiple of $d");
                expect(10)->toBeMultipleOf(5);
            });
        });
        $ctx->setWorld(new Subject());
        $result = $ctx->run();
        expect($result->isError())->toBe(false);
    });

    it("resolves let mocks in beforeEach hooks", function() {
        $resolved = null;
        $ctx = new Context("let mock resolve", function() use (&$resolved) {
            let(fn(\JsonSerializable $serializer) => null);
            beforeEach(function(\JsonSerializable $serializer) use (&$resolved) {
                $resolved = $serializer;
            });
            it("checks", function() {
                expect(true)->toBeTrue();
            });
        });
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($resolved)->toBeAnInstanceOf(\JsonSerializable::class);
    });

    it("skips beforeAll and afterAll when pending", function() {
        $log = [];
        $ctx = new Context("pending all hooks", function() use (&$log) {
            beforeAll(function() use (&$log) {
                $log[] = 'beforeAll';
            });
            afterAll(function() use (&$log) {
                $log[] = 'afterAll';
            });
            it("pending", function() use (&$log) {
                $log[] = 'ran';
            });
        });
        $ctx->setPending(true);
        $ctx->setWorld(new Subject());
        $ctx->run();
        expect($log)->toBe([]);
    });

    it("cycles the active coverage collector around each example including hooks", function (CoverageDriver $driver) {
        $log = [];
        allow($driver->start())->toReturnUsing(function() use (&$log) {
            $log[] = 'coverage start';
        });
        allow($driver->stop())->toReturnUsing(function() use (&$log) {
            $log[] = 'coverage stop';
            return ['/project/src/App/Calculator.php' => [12 => 1]];
        });
        $collector = new PerExampleCollector($driver);
        $collector->beginSpec('spec/App/Calculator.spec.php');
        CoverageRegistry::activate($collector);

        try {
            $ctx = new Context("Calculator", function() use (&$log) {
                beforeEach(function() use (&$log) {
                    $log[] = 'beforeEach';
                });
                afterEach(function() use (&$log) {
                    $log[] = 'afterEach';
                });
                it("adds two numbers", function() use (&$log) {
                    $log[] = 'example';
                });
            });
            $ctx->setWorld(new Subject());
            $ctx->run();
        } finally {
            CoverageRegistry::reset();
        }

        expect($log)->toBe(['coverage start', 'beforeEach', 'example', 'afterEach', 'coverage stop']);
        expect($collector->getLines()['/project/src/App/Calculator.php'][12])->toBe([
            'spec/App/Calculator.spec.php::Calculator > adds two numbers',
        ]);
    });

    it("runs only the examples matching the active title filter", function () {
        FilterRegistry::activate(new TitleFilter('wanted'));
        $ran = [];

        try {
            $ctx = new Context("Filtered", function () use (&$ran) {
                it("does the wanted thing", function () use (&$ran) {
                    $ran[] = 'wanted';
                });
                it("does another thing", function () use (&$ran) {
                    $ran[] = 'other';
                });
            });
            $ctx->setWorld(new Subject());
            $result = $ctx->run();
        } finally {
            FilterRegistry::reset();
        }

        expect($ran)->toBe(['wanted']);
        expect($result->getResults())->toHaveCount(1);
    });

    it("runs only the example at the targeted line", function () {
        $ran = [];
        // The closures below must stay on single lines: the target is
        // the line of the second it() call, three lines down from here.
        $secondLine = __LINE__ + 3;
        $block = function () use (&$ran) {
            it("first", function () use (&$ran) { $ran[] = 'first'; });
            it("second", function () use (&$ran) { $ran[] = 'second'; });
        };
        LineTargetRegistry::add('ctx.spec.php', $secondLine);
        LineTargetRegistry::beginSpec('ctx.spec.php');

        try {
            $ctx = new Context("LineTargeted", $block);
            $ctx->setWorld(new Subject());
            $result = $ctx->run();
        } finally {
            LineTargetRegistry::reset();
        }

        expect($ran)->toBe(['second']);
        expect($result->getResults())->toHaveCount(1);
    });

    it("runs the example at each of several targeted lines", function () {
        $ran = [];
        $firstLine = __LINE__ + 2;
        $block = function () use (&$ran) {
            it("first", function () use (&$ran) { $ran[] = 'first'; });
            it("second", function () use (&$ran) { $ran[] = 'second'; });
            it("third", function () use (&$ran) { $ran[] = 'third'; });
        };
        LineTargetRegistry::add('ctx.spec.php', $firstLine, $firstLine + 2);
        LineTargetRegistry::beginSpec('ctx.spec.php');

        try {
            $ctx = new Context("LineTargeted", $block);
            $ctx->setWorld(new Subject());
            $ctx->run();
        } finally {
            LineTargetRegistry::reset();
        }

        expect($ran)->toBe(['first', 'third']);
    });

    it("runs no more examples once a stop condition is met", function () {
        $ran = [];
        $block = function () use (&$ran) {
            it("first", function () use (&$ran) { $ran[] = 'first'; throw new \RuntimeException('boom'); });
            it("second", function () use (&$ran) { $ran[] = 'second'; });
        };
        $saved = \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher();
        \PhpSpec\EventDispatcher\DispatcherRegistry::reset();
        StopRegistry::activate(new StopConditions(onError: true));

        try {
            $ctx = new Context("Halting", $block);
            $ctx->setWorld(new Subject());
            $result = $ctx->run();
        } finally {
            StopRegistry::reset();
            \PhpSpec\EventDispatcher\DispatcherRegistry::set($saved);
        }

        expect($ran)->toBe(['first']);
        expect($result->getResults())->toHaveCount(1);
    });

    it("runs the whole context when the targeted line is inside it but on no example", function () {
        $ran = [];
        $blockLine = __LINE__ + 1;
        $block = function () use (&$ran) {
            it("first", function () use (&$ran) { $ran[] = 'first'; });
            it("second", function () use (&$ran) { $ran[] = 'second'; });
        };
        LineTargetRegistry::add('ctx.spec.php', $blockLine);
        LineTargetRegistry::beginSpec('ctx.spec.php');

        try {
            $ctx = new Context("WholeContext", $block);
            $ctx->setWorld(new Subject());
            $ctx->run();
        } finally {
            LineTargetRegistry::reset();
        }

        expect($ran)->toBe(['first', 'second']);
    });

});
