<?php

use PhpSpec\EventDispatcher\Event\ContextCreated;
use PhpSpec\EventDispatcher\Event\ContextRan;
use PhpSpec\EventDispatcher\Event\ContextStarted;
use PhpSpec\EventDispatcher\Event\ContextModified;
use PhpSpec\EventDispatcher\Event\ExampleCreated;
use PhpSpec\EventDispatcher\Event\ExampleErrored;
use PhpSpec\EventDispatcher\Event\ExampleRunned;
use PhpSpec\EventDispatcher\Event\ExampleStarted;
use PhpSpec\EventDispatcher\Event\ExpectationStarted;
use PhpSpec\EventDispatcher\Event\ExpectationFailed;
use PhpSpec\EventDispatcher\Event\ExpectationPassed;
use PhpSpec\EventDispatcher\Event\MatchCreated;
use PhpSpec\EventDispatcher\Event\Mock\MethodMocked;
use PhpSpec\EventDispatcher\Event\ExampleCompleted;
use PhpSpec\EventDispatcher\Event\SpecificationFinished;
use PhpSpec\EventDispatcher\Event\SpecificationStarted;
use PhpSpec\EventDispatcher\Event\SuiteFinished;
use PhpSpec\EventDispatcher\Event\SuiteStarted;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Specification\Context;
use PhpSpec\Specification\Example;
use PhpSpec\Specification\ExampleError;
use PhpSpec\Mock\MockedMethod;

describe("Event classes", function() {

    context("ContextCreated", function() {
        it("returns its name, title, context, and log", function() {
            $ctx = new Context("MyContext", function() {});
            $event = new ContextCreated("MyContext", $ctx);
            expect($event->getName())->toBe('context.created');
            expect($event->getTitle())->toBe("MyContext");
            expect($event->getContext())->toBe($ctx);
            expect($event->getLog())->toContain("context.created - MyContext");
        });
    });

    context("ContextRunned", function() {
        it("returns its name, title, and log", function() {
            $ctx = new Context("MyContext", function() {});
            $event = new ContextRan("MyContext", $ctx);
            expect($event->getName())->toBe('context.ran');
            expect($event->getTitle())->toBe("MyContext");
            expect($event->getLog())->toContain("context.ran - MyContext");
        });
    });

    context("ContextStarted", function() {
        it("returns its name, title, and log", function() {
            $event = new ContextStarted("MyContext");
            expect($event->getName())->toBe('context.started');
            expect($event->getTitle())->toBe("MyContext");
            expect($event->getLog())->toContain("context.started - MyContext");
        });
    });

    context("ContextModified", function() {
        it("returns its name, property, setter, and log", function() {
            $setter = fn() => 42;
            $event = new ContextModified("myProp", $setter);
            expect($event->getName())->toBe('context.modified');
            expect($event->getProperty())->toBe("myProp");
            expect($event->getSetter())->toBe($setter);
            expect($event->getLog())->toContain("context.modified - myProp");
        });
    });

    context("ExampleCreated", function() {
        it("returns its name, title, example, and log", function() {
            $ex = new Example("does something", function() {});
            $event = new ExampleCreated("does something", $ex);
            expect($event->getName())->toBe('example.created');
            expect($event->getTitle())->toBe("does something");
            expect($event->getExample())->toBe($ex);
            expect($event->getLog())->toContain("example.created - does something");
        });
    });

    context("ExampleErrored", function() {
        it("returns its name, title, error, and log", function() {
            $error = new ExampleError("broke", new \RuntimeException("broke"));
            $event = new ExampleErrored("my test", $error);
            expect($event->getName())->toBe('example.errored');
            expect($event->getTitle())->toBe("my test");
            expect($event->getError())->toBeAnInstanceOf(ExampleError::class);
            expect($event->getLog())->toContain("example.errored - my test");
        });
    });

    context("ExampleRunned", function() {
        it("returns its name, title, and log", function() {
            $event = new ExampleRunned("my test");
            expect($event->getName())->toBe('example.runned');
            expect($event->getTitle())->toBe("my test");
            expect($event->getLog())->toContain("example.runned - my test");
        });
    });

    context("ExampleStarted", function() {
        it("returns its name, title, and log", function() {
            $event = new ExampleStarted("my test");
            expect($event->getName())->toBe('example.started');
            expect($event->getTitle())->toBe("my test");
            expect($event->getLog())->toContain("example.started - my test");
        });
    });

    context("ExpectationStarted", function() {
        it("returns its name", function() {
            $event = new ExpectationStarted();
            expect($event->getName())->toBe('expectation.started');
        });
    });

    context("ExpectationFailed", function() {
        it("returns its name", function() {
            $event = new ExpectationFailed();
            expect($event->getName())->toBe('expectation.failed');
        });
    });

    context("ExpectationPassed", function() {
        it("returns its name", function() {
            $event = new ExpectationPassed();
            expect($event->getName())->toBe('expectation.passed');
        });
    });

    context("MatchCreated", function() {
        it("returns its name and match closure", function() {
            $match = fn() => true;
            $event = new MatchCreated($match);
            expect($event->getName())->toBe('match.created');
            expect($event->getMatch())->toBe($match);
        });
    });

    context("MethodMocked", function() {
        it("returns its name, double, mocked method, and prediction", function() {
            $double = new \stdClass();
            $method = new MockedMethod($double, "save");
            $event = new MethodMocked($double, $method, 1);
            expect($event->getName())->toBe('method.mocked');
            expect($event->getDouble())->toBe($double);
            expect($event->getMockedMethod())->toBe($method);
            expect($event->getPrediction())->toBe(1);
        });
    });

    context("SpecificationStarted", function() {
        it("returns its name, path, and log", function() {
            $event = new SpecificationStarted("/path/to/spec.php");
            expect($event->getName())->toBe('specification.started');
            expect($event->getPath())->toBe("/path/to/spec.php");
            expect($event->getLog())->toContain("specification.started - /path/to/spec.php");
        });
    });

    context("SpecificationFinished", function() {
        it("returns its name, title, and log", function() {
            $event = new SpecificationFinished("Calculator");
            expect($event->getName())->toBe('specification.finished');
            expect($event->getTitle())->toBe("Calculator");
            expect($event->getLog())->toContain("specification.finished - Calculator");
        });
    });

    context("SuiteStarted", function() {
        it("returns its name and log", function() {
            $event = new SuiteStarted();
            expect($event->getName())->toBe('suite.started');
            expect($event->getLog())->toContain("suite.started");
        });
    });

    context("SuiteFinished", function() {
        it("returns its name and log", function() {
            $event = new SuiteFinished();
            expect($event->getName())->toBe('suite.finished');
            expect($event->getLog())->toContain("suite.finished");
        });
    });

    context("ExampleCompleted", function() {
        it("returns its name, title, result, and log", function() {
            $result = new ExampleResult("does something", []);
            $event = new ExampleCompleted("does something", $result);
            expect($event->getName())->toBe('example.completed');
            expect($event->getTitle())->toBe("does something");
            expect($event->getResult())->toBe($result);
            expect($event->getLog())->toContain("example.completed - does something");
        });
    });

});
