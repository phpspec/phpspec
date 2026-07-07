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
use PhpSpec\Coverage\CoverageRegistry;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ContextRan;
use PhpSpec\EventDispatcher\Event\ContextStarted;
use PhpSpec\FilterRegistry;
use PhpSpec\LineTargetRegistry;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Results;
use ReflectionException;
use ReflectionFunction;
use Throwable;

/**
 * @internal
 * Represents a describe()/context() block containing examples, nested contexts,
 * lifecycle hooks, and shared world state.
 */
class Context implements ExampleRegistry, SpecBlock
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

                $results[] = $block instanceof Example && !$block->isPending()
                    ? $this->runExampleWithHooks($block)
                    : $block->run();
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

        $this->reapplyLets();
        foreach ($this->beforeEachHooks as $hook) {
            $hook(...$this->resolveClosureArgs($hook));
        }
        $this->world->__phpspec_let_mocks = $this->letMocks;

        $result = $example->run();

        foreach ($this->afterEachHooks as $hook) {
            $hook(...$this->resolveClosureArgs($hook));
        }
        $coverage?->endExample($result->getTitle());

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
                    $block->setPending(true);
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
        $line = LineTargetRegistry::currentTarget();

        if ($line === null) {
            return;
        }

        $containing = array_values(array_filter(
            $this->specBlocks,
            fn(SpecBlock $block) => ($block instanceof Example || $block instanceof Context)
                && $block->containsLine($line),
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
        $this->evaluateNamedLet($property, $setter);
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
        $this->evaluateInjectionLet($setter);
    }

    /**
     * Evaluates a named let binding and assigns the result to the world property.
     */
    private function evaluateNamedLet(string $property, Closure $setter): void
    {
        $this->world->$property = $setter(...$this->resolveClosureArgs($setter));
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
     * Clears world properties set by let() bindings and re-evaluates all
     * closures, creating fresh mocks for each example.
     */
    private function reapplyLets(): void
    {
        // Clear named let return values from World
        foreach ($this->letBindings as $binding) {
            if ($binding[1] !== null) {
                unset($this->world->{$binding[1]});
            }
        }
        $this->letMocks = [];
        foreach ($this->letBindings as $binding) {
            if ($binding[0] === 'named' && $binding[1] !== null) {
                $this->evaluateNamedLet($binding[1], $binding[2]);
            } else {
                $this->evaluateInjectionLet($binding[2]);
            }
        }
        // Expose letMocks so Example::resolveClosureArgs() can find them
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
                    $mock = \PhpSpec\Mock\Double::getInstance($className);
                    $this->letMocks[$name] = $mock;
                    $args[] = $mock;
                }
            }
        }
        return $args;
    }
}
