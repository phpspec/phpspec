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

namespace PhpSpec\Report\Formatter;

use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Report\Formatter\Agent\Offers;
use PhpSpec\Report\Formatter\Agent\Schema;
use PhpSpec\Report\Formatter\Agent\ValueExporter;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 * Emits the whole run as a single machine-readable JSON document for a coding
 * agent: a run_started header, one entry per example (and feature step), and a
 * summary with totals. No ANSI, no prose — the failure itself is the payload.
 */
final class Agent extends AbstractFormatter
{
    /** @var list<array<string, mixed>> the actionable entries (passing ones are omitted) */
    private array $examples = [];

    /** @var array<string, int> tally of every unit by state, including passing */
    private array $counts = [];

    /** How many spec examples ran (as opposed to story steps). */
    private int $exampleCount = 0;

    /** How many story (feature) steps ran. */
    private int $stepCount = 0;

    /** @var (\Closure(SuiteResult): mixed)|null resolves the run's generation candidates as a plain array */
    private readonly ?\Closure $resolveCandidates;

    /** The randomisation seed of this run, when order is random. */
    private ?int $seed = null;

    /** What was run, as the paths the loader was given. */
    private string $suite = 'default';

    public function __construct(OutputInterface $output, ?\Closure $resolveCandidates = null)
    {
        parent::__construct($output);
        $this->resolveCandidates = $resolveCandidates;
    }

    /**
     * Tells the formatter about the run it is rendering, so the document
     * carries the real seed (an agent reruns a flaky order with it) and what
     * was run instead of placeholders.
     */
    public function describeRun(?int $seed, string $suite): void
    {
        $this->seed = $seed;
        if ($suite !== '') {
            $this->suite = $suite;
        }
    }

    public function begin(): void {}

    public function printResult(Results $result): void
    {
        $this->collect($result, $this->subjectOf($result));
    }

    public function end(SuiteResult $results): void
    {
        $failing = $this->counts['failing'] ?? 0;
        $errors = $this->counts['error'] ?? 0;
        $pending = $this->counts['pending'] ?? 0;

        $document = [
            'suite' => [
                'v' => Schema::V,
                'event' => Schema::EVENT_RUN_STARTED,
                'suite' => $this->suite,
                'examples' => $this->exampleCount,
                'steps' => $this->stepCount,
                'seed' => $this->seed,
            ],
            'examples' => $this->examples,
            'result' => [
                'v' => Schema::V,
                'event' => Schema::EVENT_SUMMARY,
                'examples' => $this->exampleCount,
                'steps' => $this->stepCount,
                'passing' => $this->counts['passing'] ?? 0,
                'failing' => $failing,
                'errors' => $errors,
                'pending' => $pending,
                'skipped' => $this->counts['skipped'] ?? 0,
                // The one number an agent checks: everything red or unfinished
                // (failures + errors + pending). Zero means nothing to do.
                'actionable' => $failing + $errors + $pending,
                'duration_ms' => (int) round($results->getDuration() * 1000),
                'offers' => $this->offers($results),
            ],
        ];

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $this->output->write($json . "\n", false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * The run's code-generation offers as flat data, or an empty list when no
     * candidate resolver was supplied.
     *
     * @return list<array{action: string, target: string, value?: string}>
     */
    private function offers(SuiteResult $results): array
    {
        if ($this->resolveCandidates === null) {
            return [];
        }

        $candidates = ($this->resolveCandidates)($results);

        return is_array($candidates) ? Offers::fromCandidates($candidates) : [];
    }

    /**
     * The subject a result names its children after — the described class for a
     * spec, the title for a feature — or null when it carries none.
     */
    private function subjectOf(Results $result): ?string
    {
        if ($result instanceof SpecificationResult || $result instanceof FeatureResult) {
            return $result->getTitle();
        }

        return null;
    }

    /**
     * Walks the result tree, emitting one entry per example/step and threading
     * the enclosing subject down so each entry is named in full.
     */
    private function collect(Results $results, ?string $subject): void
    {
        foreach ($results->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                $this->record($this->fromExample($child, $subject), 'example');
            } elseif ($child instanceof StepResult) {
                $this->record($this->fromStep($child, $subject), 'step');
            } elseif ($child instanceof Results) {
                $this->collect($child, $this->subjectOf($child) ?? $subject);
            }
        }
    }

    /**
     * Counts an entry by state, and keeps it in the emitted list unless it
     * passed — a green suite of thousands need not spend tokens on entries an
     * agent will never act on; the summary still counts them.
     *
     * @param array<string, mixed> $entry
     * @param 'example'|'step' $kind
     */
    private function record(array $entry, string $kind): void
    {
        $state = is_string($entry['state'] ?? null) ? $entry['state'] : 'passing';
        $this->counts[$state] = ($this->counts[$state] ?? 0) + 1;

        if ($kind === 'step') {
            $this->stepCount++;
        } else {
            $this->exampleCount++;
        }

        if ($state !== 'passing') {
            $this->examples[] = $entry;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fromExample(ExampleResult $example, ?string $subject): array
    {
        $state = $this->exampleState($example);
        $name = $this->name($subject, $example->getTitle());
        $entry = [
            'v' => Schema::V,
            'id' => $this->identify($name),
            'example' => $name,
            'state' => $state,
        ];

        if ($state === 'failing') {
            $match = $this->failingMatch($example);
            // phpspec names these from the matcher's point of view — getExpected()
            // is the expect() subject (the value the code produced) and getActual()
            // is the matcher's argument (the target). The agent contract uses the
            // universal convention, so they are swapped here: actual = the subject,
            // expected = the target.
            $entry['expected'] = [
                'matcher' => $match?->getMatcher(),
                'value' => ValueExporter::export($match?->getActual()),
                'negated' => $match?->isNegated() ?? false,
            ];
            $entry['actual'] = ValueExporter::export($match?->getExpected());
            $entry['message'] = $example->getMessage();
            $location = $this->location($match?->getFile(), $match?->getLine());
            $entry['spec'] = $location;
            $this->addRerun($entry, $location);
            // No offer on a failure: the code exists and the behaviour is wrong,
            // there is nothing to generate — `state: failing` already says so.
        } elseif ($state === 'error') {
            $error = $example->getError();
            $entry['exception'] = [
                'class' => $error?->getType(),
                'message' => $error?->getMessage(),
                'at' => $this->location($error?->getFile(), $error?->getLine()),
            ];
            $location = $this->location($error?->getFile(), $error?->getLine());
            $entry['spec'] = $location;
            $this->addRerun($entry, $location);
            // A missing class/method/interface the error names becomes a concrete
            // offer to generate it, right on the example that hit it. Only present
            // when the error actually maps to something a generator can create.
            $offer = Offers::forError($error?->getMessage() ?? '');
            if ($offer !== null) {
                $entry['offer'] = $offer;
            }
        }

        $this->attachNotes($entry, $example);

        return $entry;
    }

    /**
     * Attaches any PHP warnings, deprecations or notices the example collected,
     * as lean {message, at} lists and only when non-empty — a clean entry stays
     * clean. A deprecation is sometimes the very clue that explains a failure.
     *
     * @param array<string, mixed> $entry
     */
    private function attachNotes(array &$entry, ExampleResult $example): void
    {
        $warnings = $this->notes($example->getWarnings());
        if ($warnings !== []) {
            $entry['warnings'] = $warnings;
        }

        $deprecations = $this->notes($example->getDeprecations());
        if ($deprecations !== []) {
            $entry['deprecations'] = $deprecations;
        }

        $notices = $this->notes($example->getNotices());
        if ($notices !== []) {
            $entry['notices'] = $notices;
        }
    }

    /**
     * Reduces raw {severity, message, file, line} items to the agent-facing
     * {message, at} shape — the severity flag is noise once the note's kind is
     * named by its key.
     *
     * @param array<array{severity: int, message: string, file: string, line: int}> $items
     * @return list<array{message: string, at: string|null}>
     */
    private function notes(array $items): array
    {
        $notes = [];
        foreach ($items as $item) {
            $notes[] = [
                'message' => $item['message'],
                'at' => $this->location($item['file'], $item['line']),
            ];
        }

        return $notes;
    }

    /**
     * Maps an example to its agent state, in the precedence the counter uses.
     */
    private function exampleState(ExampleResult $example): string
    {
        return match (true) {
            $example->isPending() => 'pending',
            $example->isSkipped() => 'skipped',
            $example->isError() => 'error',
            $example->isFailure() => 'failing',
            default => 'passing',
        };
    }

    /**
     * The first failing expectation of an example, or null when none failed.
     */
    private function failingMatch(ExampleResult $example): ?MatchResult
    {
        foreach ($example->getResults() as $match) {
            if ($match->isFailure()) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fromStep(StepResult $step, ?string $subject): array
    {
        $state = match (true) {
            $step->isPending() || $step->isUndefined() => 'pending',
            $step->isFailure() => 'failing',
            $step->isSkipped() => 'skipped',
            default => 'passing',
        };

        $name = $this->name($subject, $step->getTitle());
        $entry = [
            'v' => Schema::V,
            'id' => $this->identify($name),
            'example' => $name,
            'state' => $state,
        ];

        if ($state === 'failing' && $step->getError() !== null) {
            $entry['message'] = $step->getError()->getMessage();
        }

        return $entry;
    }

    /**
     * Joins the subject and the title into the example's full name.
     */
    private function name(?string $subject, string $title): string
    {
        return $subject !== null && $subject !== '' ? $subject . ' ' . $title : $title;
    }

    /**
     * A stable, compact identifier for an example, derived from its full name
     * (described subject + title) — the one part of an entry that survives edits
     * moving lines or shifting where a failure fires (an error location often
     * points into src/, not the spec). Lets an agent ask "is THIS exact failure
     * still here?" across runs; recomputable from the emitted `example` field.
     */
    private function identify(string $name): string
    {
        return substr(sha1($name), 0, 12);
    }

    /**
     * Attaches the exact line-targeted command that re-runs just this one
     * example, so an agent can verify a single fix without a full-suite run.
     * phpspec resolves a "spec.php:LINE" path to the example whose closure spans
     * that line, and the expectation/error site always falls inside it — so the
     * entry's own location is a valid target. Omitted when there is none.
     *
     * @param array<string, mixed> $entry
     */
    private function addRerun(array &$entry, ?string $location): void
    {
        if ($location !== null) {
            $entry['rerun'] = 'run ' . $location;
        }
    }

    /**
     * Renders a file:line as a project-relative, forward-slashed location, or
     * null when either part is missing.
     */
    private function location(?string $file, ?int $line): ?string
    {
        if ($file === null || $line === null) {
            return null;
        }

        // Normalise both sides to forward slashes before comparing: on Windows
        // getcwd() yields backslashes while a file path may already carry
        // forward slashes, so a raw DIRECTORY_SEPARATOR prefix check would miss
        // and leak the absolute path.
        $file = str_replace('\\', '/', $file);
        $cwd = getcwd();
        if ($cwd !== false) {
            $cwd = str_replace('\\', '/', $cwd);
            if (str_starts_with($file, $cwd . '/')) {
                $file = substr($file, strlen($cwd) + 1);
            }
        }

        return $file . ':' . $line;
    }
}
