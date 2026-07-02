<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\Specification\Context;
use PhpSpec\Specification\Subject;
use PhpSpec\Result\ContextResult;

describe(Context::class, function() {

    it("instantiates", function() {
        $ctx = new Context("test context", function() {});
        expect($ctx)->toBeAnInstanceOf(Context::class);
    });

    it("runs its closure and returns a ContextResult", function() {
        $ctx = new Context("test context", function() {});
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
        $result = $ctx->run();
        expect($ran)->toBe(true);
        expect($result->getResults())->toHaveCount(1);
    });

    it("catches errors in closure", function() {
        $ctx = new Context("broken", function() {
            throw new \RuntimeException("boom");
        });
        $ctx->setWorld(new Subject(__FILE__));
        $result = $ctx->run();
        expect($result->isError())->toBe(true);
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
        $result = $ctx->run();
        expect($log)->toBe(['focused']);
        expect($result->getResults())->toHaveCount(2);
        expect($result->getResults()[0]->isPending())->toBe(true);
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
        $result = $ctx->run();
        expect($result->isError())->toBe(false);
    });

    it("uses its function as alias for it", function() {
        $ran = false;
        $ctx = new Context("its test", function() use (&$ran) {
            its("works via its", function() use (&$ran) {
                $ran = true;
            });
        });
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
        $result = $ctx->run();
        // The nested context should have 1 child result
        $nestedCtx = $result->getResults()[0];
        expect($nestedCtx->getResults())->toHaveCount(1);
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
        $ctx->setWorld(new Subject(__FILE__));
        $ctx->run();
        // let should be called once during describe loading + once per example re-apply = 3 total
        expect($counter)->toBeGreaterThanOrEqualTo(2);
    });

    it("addMatcher registers custom matchers", function() {
        $ctx = new Context("custom matcher", function() {
            it("uses custom matcher", function() {
                addMatcher('toBeEven', fn($v) => $v % 2 === 0, 'Expected %s to be even');
                expect(4)->toBeEven();
            });
        });
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
        $ctx->setWorld(new Subject(__FILE__));
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
            $ctx->setWorld(new Subject(__FILE__));
            $ctx->run();
        } finally {
            CoverageRegistry::reset();
        }

        expect($log)->toBe(['coverage start', 'beforeEach', 'example', 'afterEach', 'coverage stop']);
        expect($collector->getLines()['/project/src/App/Calculator.php'][12])->toBe([
            'spec/App/Calculator.spec.php::Calculator > adds two numbers',
        ]);
    });

});
