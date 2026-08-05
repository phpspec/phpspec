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
use PhpSpec\Attachments;
use PhpSpec\CapturedOutput;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ExampleCompleted;
use PhpSpec\EventDispatcher\Event\ExampleErrored;
use PhpSpec\EventDispatcher\Event\ExampleRunned;
use PhpSpec\EventDispatcher\Event\ExampleStarted;
use PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber;
use PhpSpec\Mock\Double;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\ExampleResultRegistry;
use PhpSpec\Results;
use ReflectionException;
use ReflectionFunction;

/**
 * @internal
 * Represents a single it() example. Executes the test closure, captures
 * match results and errors, and reports timing information.
 */
class Example implements ExampleResultRegistry, Rebindable
{
    /** @var array<Closure> collected match closures from expectations */
    private array $matches = [];

    /** @var array<mixed> mock doubles created during this example */
    private array $doubles = [];

    /** @var ExampleResult accumulated result for this example */
    private ExampleResult $exampleResult;

    /** @var bool whether an error occurred during execution */
    private bool $isError = false;

    /** @var bool whether this example is pending (skipped) */
    private bool $pending = false;

    /** @var bool whether this example is focused for exclusive execution */
    private bool $focused = false;

    /**
     * @param string $title descriptive label for the example
     * @param Closure $example executable test body
     */
    public function __construct(private readonly string $title, private readonly Closure $example) {}

    /**
     * Returns a fresh, unrun copy of this example with its closure bound to the
     * given world, so a top-level it() gets a fresh world per run without
     * re-parsing the spec file.
     *
     * @param Subject $world the world to bind the copy's closure to
     * @return SpecBlock the fresh example copy
     */
    public function withWorld(Subject $world): SpecBlock
    {
        $copy = new self($this->title, Closure::bind($this->example, $world, $world));
        $copy->pending = $this->pending;
        $copy->focused = $this->focused;

        return $copy;
    }

    /**
     * Returns the descriptive label of the example.
     *
     * @return string the example title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Checks whether a line number falls within this example's closure,
     * used to resolve "file.spec.php:LINE" run targets.
     *
     * @param int $line the targeted line number
     * @return bool true when the line is within the closure's span
     */
    public function containsLine(int $line): bool
    {
        $reflection = new ReflectionFunction($this->example);

        return $line >= $reflection->getStartLine() && $line <= $reflection->getEndLine();
    }

    /**
     * Marks this example as pending (skipped).
     *
     * @param bool $pending whether to mark as pending
     * @return void
     */
    public function setPending(bool $pending): void
    {
        $this->pending = $pending;
    }

    /**
     * Marks this example as focused for exclusive execution.
     *
     * @param bool $focused whether to mark as focused
     * @return void
     */
    public function setFocused(bool $focused): void
    {
        $this->focused = $focused;
    }

    /**
     * Returns whether this example is focused.
     *
     * @return bool
     */
    public function isFocused(): bool
    {
        return $this->focused;
    }

    /**
     * Executes the example closure, dispatching lifecycle events, capturing
     * errors/warnings, and measuring execution duration.
     *
     * @return ExampleResult the match outcomes and metadata
     */
    public function run(): ExampleResult
    {
        $subscriber = new ExampleSubscriber($this);
        DispatcherRegistry::dispatcher()->addSubscriber($subscriber);

        // Read while the example is still standing where it attached them, which
        // is before its afterEach hooks tidy away what they point at.
        $attachments = new Attachments();
        DispatcherRegistry::dispatcher()->addSubscriber($attachments);

        DispatcherRegistry::dispatcher()->dispatch(new ExampleStarted($this->title), ExampleStarted::NAME);

        if ($this->pending) {
            DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
            $this->exampleResult = new ExampleResult($this->title, [], false, true);
            $this->keepAttachments($attachments);
            DispatcherRegistry::dispatcher()->dispatch(new ExampleCompleted($this->title, $this->exampleResult), ExampleCompleted::NAME);
            return $this->exampleResult;
        }

        $warnings = [];
        set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$warnings) {
            $warnings[] = [
                'severity' => $severity,
                'message' => $message,
                'file' => $file,
                'line' => $line,
            ];
            return true;
        }, E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED);

        // What the subject prints belongs to the example that provoked it, not
        // to whatever the terminal happened to be showing at the time.
        $printed = new CapturedOutput();

        $start = hrtime(true);
        try {
            $printed->around(fn() => ($this->example)(...$this->resolveClosureArgs($this->example)));
        } catch (PendingException $e) {
            restore_error_handler();
            DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
            $this->exampleResult = new ExampleResult($this->title, [], false, true);
            $this->exampleResult->setWarnings($warnings);
            $this->exampleResult->setOutput($printed->text());
            $this->keepAttachments($attachments);
            DispatcherRegistry::dispatcher()->dispatch(new ExampleCompleted($this->title, $this->exampleResult), ExampleCompleted::NAME);
            return $this->exampleResult;
        } catch (SkippedException $e) {
            restore_error_handler();
            DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
            $this->exampleResult = new ExampleResult($this->title, [], false, false, true);
            $this->exampleResult->setOutput($printed->text());
            $this->keepAttachments($attachments);
            DispatcherRegistry::dispatcher()->dispatch(new ExampleCompleted($this->title, $this->exampleResult), ExampleCompleted::NAME);
            return $this->exampleResult;
        } catch (\Throwable $e) {
            DispatcherRegistry::dispatcher()->dispatch(
                new ExampleErrored($this->title, new ExampleError($e->getMessage(), $e)),
                ExampleErrored::NAME,
            );
        }

        $elapsed = (hrtime(true) - $start) / 1e9;
        DispatcherRegistry::dispatcher()->dispatch(new ExampleRunned($this->title), ExampleRunned::NAME);
        restore_error_handler();
        DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
        $this->exampleResult->setDuration($elapsed);
        $this->exampleResult->setOutput($printed->text());
        $unique = [];
        foreach ($warnings as $w) {
            $key = $w['message'] . ':' . $w['file'] . ':' . $w['line'];
            $unique[$key] = $w;
        }
        $all = array_values($unique);
        $this->exampleResult->setWarnings(array_values(array_filter($all, fn($w) => in_array($w['severity'], [E_WARNING, E_USER_WARNING]))));
        $this->exampleResult->setDeprecations(array_values(array_filter($all, fn($w) => in_array($w['severity'], [E_DEPRECATED, E_USER_DEPRECATED]))));
        $this->exampleResult->setNotices(array_values(array_filter($all, fn($w) => in_array($w['severity'], [E_NOTICE, E_USER_NOTICE]))));
        $this->keepAttachments($attachments);
        DispatcherRegistry::dispatcher()->dispatch(new ExampleCompleted($this->title, $this->exampleResult), ExampleCompleted::NAME);
        return $this->exampleResult;
    }

    /**
     * Puts what the example handed over about itself onto its result, and stops
     * listening for more. Read here, while the example still stands where it
     * attached them, because its afterEach hooks are next and they are what
     * tidies away the files and processes an attachment points at.
     *
     * Nothing is read for an example that passed: an attachment is a diagnosis,
     * and there is nothing to diagnose.
     */
    private function keepAttachments(Attachments $attachments): void
    {
        DispatcherRegistry::dispatcher()->removeSubscriber($attachments);

        if ($attachments->isEmpty()) {
            return;
        }

        $result = $this->exampleResult;
        if ($result->isFailure() || $result->isError() || $result->isPending() || $result->isSkipped()) {
            $result->setAttachments($attachments->read());
        }
    }

    /**
     * Registers a match closure from an expectation.
     *
     * @param Closure $match deferred matcher evaluation
     * @return void
     */
    public function addMatch(Closure $match): void
    {
        $this->matches[] = $match;
    }

    /**
     * Returns all registered match closures.
     *
     * @return array<Closure>
     */
    public function getMatches(): array
    {
        return $this->matches;
    }

    /**
     * Records an error on the example result.
     *
     * @param ExampleError $error the captured error
     * @return void
     */
    public function setError(ExampleError $error): void
    {
        $this->exampleResult->setError($error);
    }

    /**
     * Replaces the current example result with the given one.
     * Sets the error flag if the result indicates an error.
     *
     * @param ExampleResult $result the new example result
     * @return void
     */
    public function addExampleResult(ExampleResult $result): void
    {
        if ($result->isError()) {
            $this->isError = true;
        }

        $this->exampleResult = $result;
    }

    /**
     * Clears all registered match closures.
     *
     * @return void
     */
    public function resetMatches(): void
    {
        $this->matches = [];
    }

    /**
     * Returns whether this example is pending (skipped).
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->pending;
    }

    /**
     * Returns whether an error occurred during execution.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->isError;
    }

    /**
     * Tracks a mock double created during this example.
     *
     * @param mixed $double mock double instance
     * @return void
     */
    public function addDouble(mixed $double): void
    {
        $this->doubles[] = $double;
    }

    /**
     * Resolves type-hinted closure parameters to let-created mocks, world properties,
     * or new mock doubles.
     *
     * @param Closure $closure example closure whose parameters to resolve
     * @return array<mixed> resolved arguments
     * @throws ReflectionException
     */
    private function resolveClosureArgs(Closure $closure): array
    {
        $ref = new ReflectionFunction($closure);
        $world = $ref->getClosureThis();
        $letMocks = ($world !== null && isset($world->__phpspec_let_mocks))
            ? $world->__phpspec_let_mocks : [];
        $args = [];
        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $name = $param->getName();
                $className = $type->getName();
                if (isset($letMocks[$name]) && $letMocks[$name] instanceof $className) {
                    $args[] = $letMocks[$name];
                } elseif ($world !== null && isset($world->$name) && $world->$name instanceof $className) {
                    $args[] = $world->$name;
                } else {
                    $args[] = Double::getInstance($className);
                }
            }
        }
        return $args;
    }
}
