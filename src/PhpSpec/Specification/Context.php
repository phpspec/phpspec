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

namespace PhpSpec\Specification;

use Closure;
use PhpSpec\CapturedOutput;
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ContextRan;
use PhpSpec\EventDispatcher\Event\ContextStarted;
use PhpSpec\FilterRegistry;
use PhpSpec\LineTargetRegistry;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Results;
use PhpSpec\StopRegistry;
use ReflectionException;
use ReflectionFunction;
use Throwable;

/**
 * @internal
 * Represents a describe()/context() block containing examples, nested contexts,
 * lifecycle hooks, and shared world state.
 */
class Context implements ExampleRegistry, Rebindable
{
    /** @var array<SpecBlock> child examples and nested contexts */
    private array $specBlocks = [];

    /** @var array<Closure> hooks executed once before all examples */
    private array $beforeAllHooks = [];

    /** @var array<Closure> hooks executed once after all examples */
    private array $afterAllHooks = [];

    /** @var array<Closure> hooks executed before each example */
    private array $beforeEachHooks = [];

    /** @var array<Closure> hooks executed after each example */
    private array $afterEachHooks = [];

    /** @var array<array{0: string, 1: ?string, 2: Closure}> stored let() bindings for per-example reset */
    private array $letBindings = [];

    /** @var array<string, object> mocks created by let() parameter injection, keyed by param name */
    private array $letMocks = [];

    /** @var Subject shared state object accessible via $this in closures */
    private Subject $world;

    /** @var bool whether all examples in this context are skipped */
    private bool $pending = false;

    /** @var bool whether this context is focused (exclusive execution) */
    private bool $focused = false;

    /** @var ExampleError error captured during context execution */
    private ExampleError $error;

    /**
     * @param mixed $context description string or class name
     * @param Closure $specBlock closure containing nested DSL calls
     */
    public function __construct(private readonly mixed $context, private readonly Closure $specBlock) {}

    /**
     * Returns a fresh, unrun copy of this context with its block closure bound
     * to the given world. The nested it()/let()/hook closures the block defines
     * inherit that binding when the copy runs, so every run gets its own world
     * without re-parsing the spec file.
     *
     * @param Subject $world the world to bind the copy's closure to
     * @return SpecBlock the fresh context copy
     */
    public function withWorld(Subject $world): SpecBlock
    {
        $copy = new self($this->context, Closure::bind($this->specBlock, $world, $world));
        $copy->pending = $this->pending;
        $copy->focused = $this->focused;

        return $copy;
    }

    /**
     * Marks this context and all its children as pending (skipped).
     *
     * @param bool $pending whether to mark as pending
     * @return void
     */
    public function setPending(bool $pending): void
    {
        $this->pending = $pending;
    }

    /**
     * Marks this context as focused for exclusive execution.
     *
     * @param bool $focused whether to mark as focused
     * @return void
     */
    public function setFocused(bool $focused): void
    {
        $this->focused = $focused;
    }

    /**
     * Returns whether this context is focused.
     *
     * @return bool
     */
    public function isFocused(): bool
    {
        return $this->focused;
    }

    /**
     * Executes the context: evaluates the spec block closure, resolves focus,
     * runs lifecycle hooks, and collects results from child blocks.
     *
     * @return Results ContextResult containing all child results
     */
    public function run(): Results
    {
        $results = [];
        CoverageRegistry::collector()?->pushContext($this->context);

        try {
            DispatcherRegistry::dispatcher()->dispatch(new ContextStarted($this->context), ContextStarted::NAME);
            DispatcherRegistry::dispatcher()->pushScope($this);
            ($this->specBlock)();
            DispatcherRegistry::dispatcher()->popScope();

            $this->resolveFocus();
            $this->applyTitleFilter();
            $this->applyLineFilter();

            if (!$this->pending) {
                foreach ($this->beforeAllHooks as $hook) {
                    $hook();
                }
            }

            foreach ($this->specBlocks as $block) {
                if ($this->pending && ($block instanceof Context || $block instanceof Example)) {
                    $block->setPending(true);
                }

                if ($block instanceof Context) {
                    $block->setWorld($this->world);
                    $block->inheritHooks($this->beforeEachHooks, $this->afterEachHooks, $this->letBindings);
                }

                $result = $block instanceof Example && !$block->isPending()
                    ? $this->runExampleWithHooks($block)
                    : $block->run();
                $results[] = $result;

                if (StopRegistry::reached($result)) {
                    break;
                }
            }

            if (!$this->pending) {
                foreach ($this->afterAllHooks as $hook) {
                    $hook();
                }
            }
        } catch (Throwable $e) {
            DispatcherRegistry::dispatcher()->dispatch(new ContextRan($this->context, $this), ContextRan::NAME);
            $result = new ExampleResult($this->context, [], true);
            $error = new ExampleError($e->getMessage(), $e);
            $result->setError($error);
            $contextResult = new ContextResult($this->context, [$result]);
            $contextResult->setError($error);

            return $contextResult;
        } finally {
            CoverageRegistry::collector()?->popContext();
        }

        DispatcherRegistry::dispatcher()->dispatch(new ContextRan($this->context, $this), ContextRan::NAME);

        return new ContextResult($this->context, $results);
    }

    /**
     * Runs a single example surrounded by its beforeEach/afterEach hooks and
     * let bindings. When per-example coverage is active, the whole sequence
     * (setup, example, teardown) is collected and attributed to the example,
     * so that code exercised only during setup still counts as covered by it.
     *
     * @param Example $example the example to run
     * @return Results the example result
     * @throws ReflectionException
     */
    private function runExampleWithHooks(Example $example): Results
    {
        $coverage = CoverageRegistry::collector();
        $coverage?->beginExample();

        // What the hooks print belongs to the example they wrap, next to what
        // its body printed, not to the terminal between two results.
        $setup = new CapturedOutput();
        $teardown = new CapturedOutput();

        try {
            $setup->around(function (): void {
                $this->reapplyLets();
                foreach ($this->beforeEachHooks as $hook) {
                    $hook(...$this->resolveClosureArgs($hook));
                }
                $this->world->__phpspec_let_mocks = $this->letMocks;
            });

            $result = $example->run();
            $body = $result->getOutput();

            try {
                $teardown->around(function (): void {
                    foreach ($this->afterEachHooks as $hook) {
                        $hook(...$this->resolveClosureArgs($hook));
                    }
                });
            } catch (Throwable $e) {
                $result = $example->failedInHook($e);
            }
        } catch (Throwable $e) {
            $coverage?->endExample($example->getTitle());

            return $this->printed($example->failedInHook($e), $setup->text(), '');
        } finally {
            $this->forgetInjectedMocks();
        }

        $coverage?->endExample($result->getTitle());

        return $this->printed($result, $setup->text() . $body, $teardown->text());
    }

    /**
     * Takes the mocks this example was handed off the world, so no example
     * after it, in this context or a nested one sharing the world, inherits a
     * double that carries this example's stubs.
     */
    private function forgetInjectedMocks(): void
    {
        foreach (array_keys($this->letMocks) as $name) {
            unset($this->world->$name);
        }

        $this->world->__phpspec_pending_lets = [];
    }

    private function printed(ExampleResult $result, string $before, string $after): ExampleResult
    {
        $result->setOutput($before . $after);

        return $result;
    }

    /**
     * Registers a child spec block (example or nested context).
     *
     * @param SpecBlock $example child block to add
     * @return void
     */
    public function addSpecBlock(SpecBlock $example): void
    {
        $this->specBlocks[] = $example;
    }

    /**
     * Sets the shared world (Subject) for let() bindings and closure $this.
     *
     * @param Subject $world shared state container
     * @return void
     */
    public function setWorld(Subject $world): void
    {
        $this->world = $world;
    }

    /**
     * Records an error that occurred during context execution.
     *
     * @param ExampleError $error the captured error
     * @return void
     */
    public function setError(ExampleError $error): void
    {
        $this->error = $error;
    }

    /**
     * Returns whether an error was recorded during execution.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return isset($this->error);
    }

    /**
     * Registers a beforeAll hook to run once before all examples.
     *
     * @param Closure $hook setup callback
     * @return void
     */
    public function addBeforeAll(Closure $hook): void
    {
        $this->beforeAllHooks[] = $hook;
    }

    /**
     * Registers an afterAll hook to run once after all examples.
     *
     * @param Closure $hook teardown callback
     * @return void
     */
    public function addAfterAll(Closure $hook): void
    {
        $this->afterAllHooks[] = $hook;
    }

    /**
     * Registers a beforeEach hook to run before each example.
     *
     * @param Closure $hook per-example setup callback
     * @return void
     */
    public function addBeforeEach(Closure $hook): void
    {
        $this->beforeEachHooks[] = $hook;
    }

    /**
     * Registers an afterEach hook to run after each example.
     *
     * @param Closure $hook per-example teardown callback
     * @return void
     */
    public function addAfterEach(Closure $hook): void
    {
        $this->afterEachHooks[] = $hook;
    }

    /**
     * Inherits beforeEach/afterEach hooks and let bindings from a parent context.
     * Parent before hooks are prepended; parent after hooks are appended.
     *
     * @param array<Closure> $before parent's beforeEach hooks
     * @param array<Closure> $after parent's afterEach hooks
     * @param array<array{0: string, 1: ?string, 2: Closure}> $letBindings parent's let() bindings
     * @return void
     */
    public function inheritHooks(array $before, array $after, array $letBindings = []): void
    {
        $this->beforeEachHooks = array_merge($before, $this->beforeEachHooks);
        $this->afterEachHooks = array_merge($this->afterEachHooks, $after);
        $this->letBindings = array_merge($letBindings, $this->letBindings);
    }

    /**
     * Resolves focus within this context: if any child is focused,
     * non-focused siblings are marked as pending.
     */
    private function resolveFocus(): void
    {
        if ($this->focused) {
            return;
        }

        $hasFocusedChild = false;
        foreach ($this->specBlocks as $block) {
            if (($block instanceof Example || $block instanceof Context) && $block->isFocused()) {
                $hasFocusedChild = true;
                break;
            }
        }

        if ($hasFocusedChild) {
            foreach ($this->specBlocks as $block) {
                if ($block instanceof Example && !$block->isFocused()) {
                    $block->leaveOutByFocus();
                }
            }
        }
    }

    /**
     * Removes examples whose title does not match the active title filter.
     * Unlike focus, filtered examples are removed rather than marked pending,
     * so they do not appear in the output at all. Nested contexts are kept;
     * they prune their own examples when they run.
     */
    private function applyTitleFilter(): void
    {
        $filter = FilterRegistry::current();

        if ($filter === null) {
            return;
        }

        $this->specBlocks = array_values(array_filter(
            $this->specBlocks,
            fn(SpecBlock $block) => !($block instanceof Example) || $filter->matches($block->getTitle()),
        ));
    }

    /**
     * Reduces this context to the block at the targeted line, when one is set
     * for the running spec file. If a child example or context spans the line,
     * only the spanning children are kept; otherwise the line addresses this
     * context as a whole and every child runs.
     */
    private function applyLineFilter(): void
    {
        $lines = LineTargetRegistry::currentTargets();

        if ($lines === []) {
            return;
        }

        $containing = array_values(array_filter(
            $this->specBlocks,
            fn(SpecBlock $block) => ($block instanceof Example || $block instanceof Context)
                && array_filter($lines, $block->containsLine(...)) !== [],
        ));

        if ($containing !== []) {
            $this->specBlocks = $containing;
        }
    }

    /**
     * Checks whether a line number falls within this context's closure,
     * used to resolve "file.spec.php:LINE" run targets.
     *
     * @param int $line the targeted line number
     * @return bool true when the line is within the closure's span
     */
    public function containsLine(int $line): bool
    {
        $reflection = new ReflectionFunction($this->specBlock);

        return $line >= $reflection->getStartLine() && $line <= $reflection->getEndLine();
    }

    /**
     * Sets a named property on the world by invoking the setter closure.
     * Closure parameters with type hints are auto-resolved as mock doubles.
     *
     * @param string $property property name on the world
     * @param Closure $setter factory closure returning the value
     * @return void
     */
    public function modify(string $property, Closure $setter): void
    {
        $this->letBindings[] = ['named', $property, $setter];
    }

    /**
     * Invokes a closure with auto-resolved mock doubles for its type-hinted parameters.
     * Used by let() when called with a single closure argument.
     *
     * @param Closure $setter injection closure
     * @return void
     */
    public function modifyWithInjection(Closure $setter): void
    {
        $this->letBindings[] = ['injection', null, $setter];
    }


    /**
     * Evaluates an injection-style let binding, resolving type-hinted mock parameters.
     */
    private function evaluateInjectionLet(Closure $setter): void
    {
        $args = $this->resolveClosureArgs($setter);
        if (!empty($args)) {
            $setter(...$args);
        }
    }

    /**
     * Forgets the previous example's let values and arranges this example's.
     * A named let is built when the example first reads it, after the hooks;
     * the doubles it asks for are made now, so the hooks and the example find
     * them on $this before the value exists. An injection let runs now, since
     * its doubles are all it is for.
     */
    private function reapplyLets(): void
    {
        foreach ($this->letBindings as $binding) {
            if ($binding[1] !== null) {
                unset($this->world->{$binding[1]});
            }
        }
        $this->letMocks = [];
        $this->world->__phpspec_pending_lets = [];

        foreach ($this->letBindings as [$kind, $property, $setter]) {
            if ($kind === 'named' && $property !== null) {
                $args = $this->resolveClosureArgs($setter);
                $this->world->__phpspec_pending_lets[$property] = static fn(): mixed => $setter(...$args);
            } else {
                $this->evaluateInjectionLet($setter);
            }
        }

        $this->world->__phpspec_let_mocks = $this->letMocks;
    }

    /**
     * Resolves type-hinted closure parameters to existing letMocks, world properties,
     * or new mock doubles. New mocks are stored in $letMocks only (not on World).
     *
     * @param Closure $closure closure whose parameters to resolve
     * @return array<mixed> resolved arguments
     * @throws ReflectionException
     */
    private function resolveClosureArgs(Closure $closure): array
    {
        $ref = new \ReflectionFunction($closure);
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $name = $param->getName();
                $className = $type->getName();
                if (isset($this->letMocks[$name]) && $this->letMocks[$name] instanceof $className) {
                    $args[] = $this->letMocks[$name];
                } elseif (isset($this->world->$name) && $this->world->$name instanceof $className) {
                    $args[] = $this->world->$name;
                } else {
                    // A mock made for a parameter is the example's to stub and
                    // verify too, under the parameter's name on $this.
                    $mock = \PhpSpec\Mock\Double::getInstance($className);
                    $this->letMocks[$name] = $mock;
                    $this->world->$name = $mock;
                    $args[] = $mock;
                }
            }
        }
        return $args;
    }
}
