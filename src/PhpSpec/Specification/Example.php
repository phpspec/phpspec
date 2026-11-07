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
use PhpSpec\CapturedNotes;
use PhpSpec\CapturedOutput;
use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Event\ExampleErrored;
use PhpSpec\EventDispatcher\Event\ExampleRunned;
use PhpSpec\EventDispatcher\Subscriber\ExampleSubscriber;
use PhpSpec\ExampleId;
use PhpSpec\Mock\HeldCalls;
use PhpSpec\OwnCode;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\ExampleResultRegistry;
use PhpSpec\Results;
use ReflectionFunction;

/**
 * @internal
 * Represents a single it() example. Executes the test closure, captures
 * match results and errors, and reports timing information.
 */
class Example implements ExampleResultRegistry, Rebindable, Titled
{
    /** @var array<Closure> collected match closures from expectations */
    private array $matches = [];

    /** @var ExampleResult accumulated result for this example */
    private ExampleResult $exampleResult;

    /** @var bool whether an error occurred during execution */
    private bool $isError = false;

    /** @var bool whether this example is pending (skipped) */
    private bool $pending = false;

    /** @var bool whether this example is focused for exclusive execution */
    private bool $focused = false;

    /** @var bool whether this example is pending only because a sibling is focused */
    private bool $leftOutByFocus = false;

    /** @var array{string, int}|null where the spec declared this example, when not where its closure is */
    private ?array $declaration = null;

    /** @var int its place among the siblings that share its title, 1 for the first */
    private int $ordinal = 1;

    /** @var list<string> the titles above this example, the spec file's first */
    private array $titles = [];

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
        $copy->leftOutByFocus = $this->leftOutByFocus;
        $copy->declaration = $this->declaration;
        $copy->ordinal = $this->ordinal;

        return $copy;
    }

    /**
     * The example's title, numbered when a sibling declared before it has the
     * same one.
     */
    public function getTitle(): string
    {
        return $this->ordinal === 1 ? $this->title : $this->title . ' #' . $this->ordinal;
    }

    public function writtenTitle(): string
    {
        return $this->title;
    }

    public function numberAmongNamesakes(int $ordinal): void
    {
        $this->ordinal = $ordinal;
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
        if ($this->declaration !== null) {
            return $this->declaration[1] === $line;
        }

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
     * Leaves this example out of the run because a sibling is focused: it is
     * pending, and says why, so the run can warn that it was focused.
     */
    public function leaveOutByFocus(): void
    {
        $this->pending = true;
        $this->leftOutByFocus = true;
    }

    /**
     * Where the spec declared this example, for one declared by its title
     * alone: there is no closure of its own to read the line off.
     */
    /**
     * Tells the example the titles above it, which its id is made of.
     *
     * @param list<string> $titles the spec file's title first, then every describe and context
     */
    public function under(array $titles): void
    {
        $this->titles = $titles;
    }

    /**
     * The id a run reports this example under, which a rerun addresses it by
     * where its line declares others too.
     */
    public function id(): string
    {
        return ExampleId::of(implode(' > ', [...$this->titles, $this->getTitle()]), $this->declaration()[0] ?? null);
    }

    /**
     * Where the example is declared: the it() call, else its closure.
     *
     * @return array{0: string, 1: int}|null
     */
    public function declaration(): ?array
    {
        if ($this->declaration !== null) {
            return $this->declaration;
        }

        $reflection = new ReflectionFunction($this->example);
        $file = $reflection->getFileName();
        $line = $reflection->getStartLine();

        return $file !== false && $line !== false ? [$file, $line] : null;
    }

    public function declaredAt(string $file, int $line): void
    {
        $this->declaration = [$file, $line];
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
     * @param Attachments|null $attachments what the example's hooks are handing over about it too, or null when it runs without them
     * @return ExampleResult the match outcomes and metadata
     */
    public function run(?Attachments $attachments = null): ExampleResult
    {
        // Whatever this example subscribes dies with it, so a leak cannot
        // collect and re-judge its siblings' matches.
        $boundary = DispatcherRegistry::dispatcher()->snapshot();

        if ($attachments === null) {
            $attachments = new Attachments();
            DispatcherRegistry::dispatcher()->addSubscriber($attachments);
        }

        try {
            return $this->declared($this->execute($attachments));
        } finally {
            DispatcherRegistry::dispatcher()->restore($boundary);
        }
    }

    private function declared(ExampleResult $result): ExampleResult
    {
        $declaration = $this->declaration();

        if ($declaration !== null) {
            $result->declaredAt(...$declaration);
        }

        return $result;
    }

    /**
     * The result of an example whose hook (a this() binding, a beforeEach, an
     * afterEach) threw: an error of its own, declared where it is, so the
     * examples beside it still run and the count holds.
     */
    public function failedInHook(ExampleError $error): ExampleResult
    {
        $this->isError = true;
        $this->exampleResult = new ExampleResult($this->getTitle(), [], true);
        $this->exampleResult->setError($error);

        return $this->declared($this->exampleResult);
    }

    /**
     * The result of an example a hook left out before it ran, with skip() or
     * pending(): skipped or pending with the hook's reason, declared where it
     * is, the body never run.
     */
    public function leftOutBy(PendingException|SkippedException $signal, ?Attachments $attachments = null): ExampleResult
    {
        $this->exampleResult = $signal instanceof PendingException
            ? new ExampleResult($this->getTitle(), [], isPending: true, reason: $signal->getMessage())
            : new ExampleResult($this->getTitle(), [], isSkipped: true, reason: $signal->getMessage());

        if ($attachments !== null) {
            $this->keepAttachments($attachments);
        }

        return $this->declared($this->exampleResult);
    }

    private function execute(Attachments $attachments): ExampleResult
    {
        $subscriber = new ExampleSubscriber($this);
        DispatcherRegistry::dispatcher()->addSubscriber($subscriber);

        if ($this->pending) {
            DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
            $this->exampleResult = new ExampleResult($this->getTitle(), [], isPending: true, reason: $this->leftOutByFocus ? 'left out by focus' : null);
            if ($this->leftOutByFocus) {
                $this->exampleResult->markLeftOutByFocus();
            }
            $this->keepAttachments($attachments);
            return $this->exampleResult;
        }

        $caught = new CapturedNotes(OwnCode::here());
        $caught->listen();

        // What the subject prints belongs to the example that provoked it, not
        // to whatever the terminal happened to be showing at the time.
        $printed = new CapturedOutput();

        $start = hrtime(true);
        try {
            $printed->around(fn() => ($this->example)(...$this->resolveClosureArgs($this->example)));
        } catch (PendingException $e) {
            $caught->stop();
            DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
            $this->exampleResult = new ExampleResult($this->getTitle(), [], isPending: true, reason: $e->getMessage());
            $this->exampleResult->raised($caught->notes());
            $this->exampleResult->setOutput($printed->text());
            $this->keepAttachments($attachments);
            return $this->exampleResult;
        } catch (SkippedException $e) {
            $caught->stop();
            DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
            $this->exampleResult = new ExampleResult($this->getTitle(), [], isSkipped: true, reason: $e->getMessage());
            $this->exampleResult->raised($caught->notes());
            $this->exampleResult->setOutput($printed->text());
            $this->keepAttachments($attachments);
            return $this->exampleResult;
        } catch (\Throwable $e) {
            // An undeclared call came first; its default answer may be what broke.
            $e = HeldCalls::take() ?? $e;
            DispatcherRegistry::dispatcher()->dispatch(
                new ExampleErrored($this->getTitle(), new ExampleError($e->getMessage(), $e)),
                ExampleErrored::NAME,
            );
        }

        $refused = HeldCalls::take();

        if ($refused !== null) {
            DispatcherRegistry::dispatcher()->dispatch(
                new ExampleErrored($this->getTitle(), new ExampleError($refused->getMessage(), $refused)),
                ExampleErrored::NAME,
            );
        }

        $elapsed = (hrtime(true) - $start) / 1e9;
        DispatcherRegistry::dispatcher()->dispatch(new ExampleRunned($this->getTitle()), ExampleRunned::NAME);
        $caught->stop();
        DispatcherRegistry::dispatcher()->removeSubscriber($subscriber);
        $this->exampleResult->setDuration($elapsed);
        $this->exampleResult->setOutput($printed->text());

        if (!$this->isError && $this->exampleResult->getResults() === []) {
            $this->exampleResult->markRisky();
        }
        $this->exampleResult->raised($caught->notes());
        $this->keepAttachments($attachments);
        return $this->exampleResult;
    }

    /**
     * Puts what was handed over about the example so far onto its result. Read
     * here, while the example still stands where it attached them, because its
     * afterEach hooks are next and they are what tidies away the files and
     * processes an attachment points at.
     *
     * Nothing is read for an example that passed: an attachment is a diagnosis,
     * and there is nothing to diagnose.
     */
    private function keepAttachments(Attachments $attachments): void
    {
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
     * What the context arranged for this example to hand its parameters. An
     * example outside any describe() has no this() and no hook to share its
     * doubles with.
     *
     * @return list<object>
     */
    private function resolveClosureArgs(Closure $closure): array
    {
        $world = (new ReflectionFunction($closure))->getClosureThis();
        $arguments = $world instanceof Subject ? $world->__phpspec_arguments : null;

        return ($arguments ?? new ExampleArguments())->for($closure);
    }
}
