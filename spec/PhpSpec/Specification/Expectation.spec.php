<?php

use PhpSpec\Specification\Expectation;

describe(Expectation::class, function() {

    it("instantiates", fn() => expect(new Expectation("value", __FILE__, __LINE__))->toBeAnInstanceOf(Expectation::class));

    it("passes toBe on strict equality", fn() => expect(42)->toBe(42));

    it("passes toBeLike on loose equality", fn() => expect("42")->toBeLike(42));

    it("passes toBeAnInstanceOf for matching class", fn() => expect(new \stdClass())->toBeAnInstanceOf(\stdClass::class));

    it("passes toHaveCount for correct count", fn() => expect([1, 2, 3])->toHaveCount(3));

    it("passes toHaveCount for empty array", fn() => expect([])->toHaveCount(0));

    it("allows chaining matchers", fn() => expect([1, 2])->toHaveCount(2)->toBeLike([1, 2]));

    context("toBe edge cases", function() {
        it("fails on type mismatch", fn() => expect("42")->toBeLike(42));

        it("handles null", fn() => expect(null)->toBe(null));

        it("handles booleans", fn() => expect(true)->toBe(true));
    });

    context("boolean matchers", function() {
        it("passes toBeTrue", fn() => expect(true)->toBeTrue());
        it("passes toBeFalse", fn() => expect(false)->toBeFalse());
    });

    context("null matcher", function() {
        it("passes toBeNull", fn() => expect(null)->toBeNull());
    });

    context("empty matcher", function() {
        it("passes toBeEmpty for empty array", fn() => expect([])->toBeEmpty());
        it("passes toBeEmpty for empty string", fn() => expect("")->toBeEmpty());
    });

    context("containment matchers", function() {
        it("passes toContain for array element", fn() => expect([1, 2, 3])->toContain(2));
        it("passes toContain for string substring", fn() => expect("hello world")->toContain("world"));
    });

    context("regex matcher", function() {
        it("passes toMatch for matching pattern", fn() => expect("hello123")->toMatch('/\d+/'));
    });

    context("comparison matchers", function() {
        it("passes toBeGreaterThan", fn() => expect(5)->toBeGreaterThan(3));
        it("passes toBeLessThan", fn() => expect(3)->toBeLessThan(5));
    });

    context("key matcher", function() {
        it("passes toHaveKey for existing key", fn() => expect(["a" => 1, "b" => 2])->toHaveKey("a"));
    });

    context("exception matcher", function() {
        it("passes toThrow for matching exception", function() {
            expect(fn() => throw new \RuntimeException("boom"))->toThrow(\RuntimeException::class);
        });

        it("passes toThrow with message check", function() {
            expect(fn() => throw new \RuntimeException("boom"))->toThrow(\RuntimeException::class, "boom");
        });

        it("does not throw when exception not thrown", function() {
            expect(fn() => "no throw")->not()->toThrow(\RuntimeException::class);
        });

        it("does not throw when wrong exception type", function() {
            expect(fn() => throw new \InvalidArgumentException("x"))->not()->toThrow(\RuntimeException::class);
        });

        it("does not match when exception has wrong message", function() {
            expect(fn() => throw new \RuntimeException("actual msg"))->not()->toThrow(\RuntimeException::class, "expected msg");
        });
    });

    context("string prefix/suffix matchers", function() {
        it("passes toStartWith for matching prefix", fn() => expect("hello world")->toStartWith("hello"));
        it("passes toEndWith for matching suffix", fn() => expect("hello world")->toEndWith("world"));
    });

    context("property matcher", function() {
        it("passes toHaveProperty for existing property", function() {
            $obj = new \stdClass();
            $obj->name = "test";
            expect($obj)->toHaveProperty("name");
        });
    });

    context("callable matcher", function() {
        it("passes toBeCallable for closure", fn() => expect(fn() => null)->toBeCallable());
        it("passes toBeCallable for function name", fn() => expect('strlen')->toBeCallable());
    });

    context("type matcher", function() {
        it("passes toBeOfType for string", fn() => expect("hello")->toBeOfType("string"));
        it("passes toBeOfType for int", fn() => expect(42)->toBeOfType("int"));
        it("passes toBeOfType for float", fn() => expect(3.14)->toBeOfType("float"));
        it("passes toBeOfType for bool", fn() => expect(true)->toBeOfType("bool"));
        it("passes toBeOfType for array", fn() => expect([])->toBeOfType("array"));
        it("passes toBeOfType for null", fn() => expect(null)->toBeOfType("null"));
        it("passes toBeOfType for class", fn() => expect(new \stdClass())->toBeOfType(\stdClass::class));
    });

    context("custom matchers", function() {
        it("registers and uses a custom matcher", function() {
            addMatcher('toBePositive', fn($value) => $value > 0, 'Expected %s to be positive');
            expect(5)->toBePositive();
        });

        it("supports custom matcher with arguments", function() {
            addMatcher('toBeDivisibleBy', fn($value, $divisor) => $value % $divisor === 0, 'Expected %s to be divisible by %s');
            expect(10)->toBeDivisibleBy(5);
        });

        it("supports negation on custom matchers", function() {
            addMatcher('toBePositive', fn($value) => $value > 0, 'Expected %s to be positive');
            expect(-1)->not()->toBePositive();
        });
    });

    context("negation", function() {
        it("passes not()->toBe() for different values", fn() => expect(1)->not()->toBe(2));

        it("passes not()->toBeTrue() for false", fn() => expect(false)->not()->toBeTrue());

        it("passes not()->toBeNull() for non-null", fn() => expect(42)->not()->toBeNull());

        it("passes not()->toContain() for missing element", fn() => expect([1, 2, 3])->not()->toContain(4));

        it("passes not()->toBeEmpty() for non-empty array", fn() => expect([1])->not()->toBeEmpty());

        it("allows chaining after not()", fn() => expect(5)->not()->toBe(3)->not()->toBe(4));
    });

    context("comparison matchers (extended)", function() {
        it("passes toBeGreaterThanOrEqualTo on equal value", fn() => expect(5)->toBeGreaterThanOrEqualTo(5));
        it("passes toBeGreaterThanOrEqualTo on greater value", fn() => expect(6)->toBeGreaterThanOrEqualTo(5));
        it("passes not()->toBeGreaterThanOrEqualTo() on lesser value", fn() => expect(4)->not()->toBeGreaterThanOrEqualTo(5));
        it("passes toBeLessThanOrEqualTo on equal value", fn() => expect(5)->toBeLessThanOrEqualTo(5));
        it("passes toBeLessThanOrEqualTo on lesser value", fn() => expect(4)->toBeLessThanOrEqualTo(5));
        it("passes not()->toBeLessThanOrEqualTo() on greater value", fn() => expect(6)->not()->toBeLessThanOrEqualTo(5));
        it("passes toBeBetween for value in range", fn() => expect(5)->toBeBetween(1, 10));
        it("passes toBeBetween for value at lower bound", fn() => expect(1)->toBeBetween(1, 10));
        it("passes toBeBetween for value at upper bound", fn() => expect(10)->toBeBetween(1, 10));
        it("passes not()->toBeBetween() for value out of range", fn() => expect(11)->not()->toBeBetween(1, 10));
    });

    context("truthiness matchers", function() {
        it("passes toBeTruthy for non-empty string", fn() => expect("hello")->toBeTruthy());
        it("passes toBeTruthy for 1", fn() => expect(1)->toBeTruthy());
        it("passes toBeTruthy for non-empty array", fn() => expect([1])->toBeTruthy());
        it("passes toBeFalsy for 0", fn() => expect(0)->toBeFalsy());
        it("passes toBeFalsy for empty string", fn() => expect("")->toBeFalsy());
        it("passes toBeFalsy for null", fn() => expect(null)->toBeFalsy());
        it("passes toBeFalsy for false", fn() => expect(false)->toBeFalsy());
        it("passes not()->toBeTruthy() for null", fn() => expect(null)->not()->toBeTruthy());
        it("passes not()->toBeFalsy() for true", fn() => expect(true)->not()->toBeFalsy());
    });

    context("numeric matchers", function() {
        it("passes toBeCloseTo for equal floats", fn() => expect(0.1 + 0.2)->toBeCloseTo(0.3));
        it("passes toBeCloseTo within delta", fn() => expect(3.14159)->toBeCloseTo(3.14160, 0.001));
        it("passes toBeCloseTo with custom delta", fn() => expect(10.0)->toBeCloseTo(10.5, 1.0));
        it("passes not()->toBeCloseTo() outside delta", fn() => expect(1.0)->not()->toBeCloseTo(2.0, 0.5));
    });

    context("length matchers", function() {
        it("passes toHaveLength for string", fn() => expect("hello")->toHaveLength(5));
        it("passes toHaveLength for empty string", fn() => expect("")->toHaveLength(0));
        it("passes toHaveLength for array", fn() => expect([1, 2, 3])->toHaveLength(3));
        it("passes toHaveLength for empty array", fn() => expect([])->toHaveLength(0));
        it("passes not()->toHaveLength() for wrong length", fn() => expect("hi")->not()->toHaveLength(5));
    });

    context("collection matchers (extended)", function() {
        it("passes toContainEqual with loose match", fn() => expect([1, 2, 3])->toContainEqual("2"));
        it("passes toContainEqual with exact match", fn() => expect(["a", "b"])->toContainEqual("a"));
        it("passes not()->toContainEqual() when absent", fn() => expect([1, 2, 3])->not()->toContainEqual(4));
    });

    context("object matchers", function() {
        it("passes toRespondTo for existing method", fn() => expect(new \ArrayObject())->toRespondTo("append"));
        it("passes toRespondTo for ArrayObject method", fn() => expect(new \ArrayObject())->toRespondTo("count"));
        it("passes not()->toRespondTo() for missing method", fn() => expect(new \stdClass())->not()->toRespondTo("nonExistentMethod"));
    });

    context("predicate matchers", function() {
        it("passes toSatisfy with truthy predicate", fn() => expect(42)->toSatisfy(fn($v) => $v % 2 === 0));
        it("passes toSatisfy with string predicate", fn() => expect("hello")->toSatisfy(fn($v) => strlen($v) > 3));
        it("passes not()->toSatisfy() with falsy predicate", fn() => expect(3)->not()->toSatisfy(fn($v) => $v % 2 === 0));
    });

    context("predicate matchers (automatic)", function() {
        it("maps toBeClosed to isClosed()", function() {
            $ticket = new class {
                public function isClosed(): bool { return true; }
            };
            expect($ticket)->toBeClosed();
        });

        it("maps toHaveLabel to hasLabel()", function() {
            $ticket = new class {
                public function hasLabel(): bool { return true; }
            };
            expect($ticket)->toHaveLabel();
        });

        it("forwards arguments to predicate method", function() {
            $ticket = new class {
                public function hasLabel(string $label): bool { return $label === 'urgent'; }
            };
            expect($ticket)->toHaveLabel('urgent');
        });

        it("works with not() negation", function() {
            $ticket = new class {
                public function isActive(): bool { return false; }
            };
            expect($ticket)->not()->toBeActive();
        });

        it("falls through to error for non-objects", function() {
            expect(fn() => expect(42)->toBeClosed())->toThrow(\BadMethodCallException::class);
        });

        it("falls through to error when method does not exist", function() {
            $obj = new \stdClass();
            expect(fn() => expect($obj)->toBeArchived())->toThrow(\BadMethodCallException::class);
        });
    });

    context("error handling", function() {
        it("throws BadMethodCallException for unknown matcher", function() {
            expect(fn() => expect(42)->toBeAwesome())->toThrow(\BadMethodCallException::class);
        });

        it("suggests similar matchers in error message", function() {
            try {
                expect(42)->toBeTru();
            } catch (\BadMethodCallException $e) {
                expect($e->getMessage())->toContain("toBeTrue");
            }
        });
    });

    context("matcher identity on the result", function() {
        it("carries the matcher name and both values onto the failed result", function() {
            $original = \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher();
            $captured = [];
            $listener = new class($captured) implements \PhpSpec\EventDispatcher\Listener {
                public function __construct(private array &$captured) {}
                public function listen(\PhpSpec\EventDispatcher\Event $event): void
                {
                    if ($event instanceof \PhpSpec\EventDispatcher\Event\MatchCreated) {
                        $this->captured[] = ($event->getMatch())();
                    }
                }
            };

            try {
                $isolated = new \PhpSpec\EventDispatcher\Dispatcher();
                $isolated->addListener($listener);
                \PhpSpec\EventDispatcher\DispatcherRegistry::set($isolated);

                (new Expectation("the haystack", __FILE__, __LINE__))->toContain("missing needle");
            } finally {
                \PhpSpec\EventDispatcher\DispatcherRegistry::set($original);
            }

            expect($captured)->toHaveCount(1);
            expect($captured[0]->getMatcher())->toBe("toContain");
            expect($captured[0]->getExpected())->toBe("the haystack");
            expect($captured[0]->getActual())->toBe("missing needle");
            expect($captured[0]->isTargetImplied())->toBeFalse();
        });

        // A matcher that names its target only in prose still states it as a
        // value, so a report never says a failure expected null when it
        // expected true, and marks it implied so a view does not read it back.
        $failureOf = function (Closure $expectation): \PhpSpec\Result\MatchResult {
            $original = \PhpSpec\EventDispatcher\DispatcherRegistry::dispatcher();
            $captured = [];
            $listener = new class($captured) implements \PhpSpec\EventDispatcher\Listener {
                public function __construct(private array &$captured) {}
                public function listen(\PhpSpec\EventDispatcher\Event $event): void
                {
                    if ($event instanceof \PhpSpec\EventDispatcher\Event\MatchCreated) {
                        $this->captured[] = ($event->getMatch())();
                    }
                }
            };

            try {
                $isolated = new \PhpSpec\EventDispatcher\Dispatcher();
                $isolated->addListener($listener);
                \PhpSpec\EventDispatcher\DispatcherRegistry::set($isolated);

                $expectation();
            } finally {
                \PhpSpec\EventDispatcher\DispatcherRegistry::set($original);
            }

            return $captured[0];
        };

        it("states the target a matcher only names in prose", function() use ($failureOf) {
            $failure = $failureOf(fn() => (new Expectation(false, __FILE__, __LINE__))->toBeTrue());

            expect($failure->getActual())->toBeTrue();
            expect($failure->getExpected())->toBeFalse();
            expect($failure->isTargetImplied())->toBeTrue();
        });

        it("says a predicate had no target rather than reporting a null one", function() use ($failureOf) {
            $failure = $failureOf(fn() => (new Expectation(3, __FILE__, __LINE__))->toSatisfy(fn($n) => $n > 10));

            expect($failure->getActual())->toBe('N/A');
            expect($failure->getExpected())->toBe(3);
            expect($failure->isTargetImplied())->toBeTrue();
        });

        it("wants the empty form of whatever it was given", function() use ($failureOf) {
            $of = fn(mixed $subject) => $failureOf(fn() => (new Expectation($subject, __FILE__, __LINE__))->toBeEmpty())->getActual();

            expect($of([1, 2]))->toBe([]);
            expect($of("stuff"))->toBe("");
            expect($of(3434))->toBe(0);
            expect($of(new \stdClass()))->toBeNull();
        });
    });

    context("formatMessage", function() {
        it("formats objects with class name and id", function() {
            $obj = new \stdClass();
            $result = Expectation::formatMessage("Expected %s", $obj);
            expect($result)->toContain("stdClass");
        });

        it("formats strings with quotes", function() {
            $result = Expectation::formatMessage("Expected %s", "hello");
            expect($result)->toBe('Expected "hello"');
        });

        it("clips a multiline string to its first line in the headline: expected/got carry it in full", function() {
            $blob = "\n  Analysing project...\n\n  Refactor App\\TodoList\n\n  Would you like me to refactor that? [Y/n] ";
            $result = Expectation::formatMessage('Expected %s to contain %s', $blob, 'run it now');

            expect($result)->toContain('to contain "run it now"');
            expect($result)->not()->toContain('Refactor App\\TodoList');
            expect($result)->toContain('…');
        });

        it("clips an overlong single-line string in the headline", function() {
            $long = str_repeat('a', 200);
            $result = Expectation::formatMessage('Expected %s', $long);

            expect(strlen($result))->toBeLessThan(100);
            expect($result)->toContain('…');
        });

        it("formats booleans as true/false", function() {
            expect(Expectation::formatMessage("Got %s", true))->toBe("Got true");
            expect(Expectation::formatMessage("Got %s", false))->toBe("Got false");
        });

        it("formats arrays with brackets", function() {
            $result = Expectation::formatMessage("Got %s", [1, 2]);
            expect($result)->toBe("Got [1, 2]");
        });

        it("formats null", function() {
            expect(Expectation::formatMessage("Got %s", null))->toBe("Got null");
        });

        it("formats integers", function() {
            expect(Expectation::formatMessage("Got %s", 42))->toBe("Got 42");
        });

        it("handles no placeholder", function() {
            expect(Expectation::formatMessage("No placeholder", "val"))->toBe("No placeholder");
        });
    });

    context("response matchers", function() {
        it("toBeOk passes for 200 response", function() {
            $response = new \PhpSpec\Browser\Response(200, '{}', ['Content-Type' => 'application/json']);
            expect($response)->toBeOk();
        });

        it("toBeBad passes for 400 response", function() {
            $response = new \PhpSpec\Browser\Response(400, '{}', ['Content-Type' => 'application/json']);
            expect($response)->toBeBad();
        });

        it("toHaveStatus passes for matching status", function() {
            $response = new \PhpSpec\Browser\Response(201, '{}', []);
            expect($response)->toHaveStatus(201);
        });

        it("toHavePath matches JSON path", function() {
            $response = new \PhpSpec\Browser\Response(200, '{"user":{"name":"John"}}', ['Content-Type' => 'application/json']);
            expect($response)->toHavePath('user.name', 'John');
        });

        it("toHaveHeader checks header existence", function() {
            $response = new \PhpSpec\Browser\Response(200, '{}', ['X-Custom' => 'test']);
            expect($response)->toHaveHeader('X-Custom');
        });

        it("toHaveHeader checks header value", function() {
            $response = new \PhpSpec\Browser\Response(200, '{}', ['X-Custom' => 'test']);
            expect($response)->toHaveHeader('X-Custom', 'test');
        });

        it("toRedirectTo checks redirect", function() {
            $response = new \PhpSpec\Browser\Response(302, '', ['Location' => '/dashboard']);
            expect($response)->toRedirectTo('/dashboard');
        });

        it("not toBeOk for non-200", function() {
            $response = new \PhpSpec\Browser\Response(404, '', []);
            expect($response)->not()->toBeOk();
        });

        it("not toBeBad for non-400", function() {
            $response = new \PhpSpec\Browser\Response(200, '', []);
            expect($response)->not()->toBeBad();
        });
    });

});
