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

use PhpSpec\Coverage\CoverageVerdict;
use PhpSpec\Report\AbstractFormatter;
use PhpSpec\Report\Formatter\Agent\Fatal;
use PhpSpec\Report\Formatter\Agent\Offers;
use PhpSpec\Report\Formatter\Agent\Origin;
use PhpSpec\Report\Formatter\Agent\ProcessEnd;
use PhpSpec\Report\Formatter\Agent\Schema;
use PhpSpec\Report\Formatter\Agent\ValueExporter;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
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

    /** The run's results, once it reached the end; null while it is still going. */
    private ?SuiteResult $results = null;

    /** Whether the document has gone out, so it goes out exactly once. */
    private bool $published = false;

    /** What the run covered, when coverage was collected. */
    private ?CoverageVerdict $coverage = null;

    /** @var array{message: string, at: string|null}|null what stopped the run short, when something did */
    private ?array $fatal = null;

    /**
     * @param OutputInterface $output the stream the document goes to
     * @param \Closure(SuiteResult): mixed|null $resolveCandidates resolves the run's generation candidates
     * @param ProcessEnd|null $processEnd the promise-keeper: given one, the document
     *                                    still goes out when the process dies mid-run
     */
    public function __construct(OutputInterface $output, ?\Closure $resolveCandidates = null, ?ProcessEnd $processEnd = null)
    {
        parent::__construct($output);
        $this->resolveCandidates = $resolveCandidates;
        $processEnd?->atEnd($this->ended(...));
    }

    /**
     * Tells the formatter what the run targets, so the header says what was
     * asked for instead of a placeholder. Told before the suite is loaded, so a
     * run that dies loading still reports it.
     */
    public function targets(string $suite): void
    {
        if ($suite !== '') {
            $this->suite = $suite;
        }
    }

    /**
     * Tells the formatter the seed the run was shuffled with, so an agent can
     * reproduce a flaky order.
     */
    public function randomisedWith(int $seed): void
    {
        $this->seed = $seed;
    }

    public function begin(): void {}

    public function printResult(Results $result): void
    {
        $this->collect($result, $this->within(new Origin(), $result));
    }

    /**
     * Takes the run's results. The document is not written here: it is the whole
     * command's artifact, not the suite runner's, so it waits for {@see publish()}
     * and can still be told what the command learns after the last example (the
     * coverage verdict).
     */
    public function end(SuiteResult $results): void
    {
        $this->results = $results;
    }

    /**
     * Takes what the coverage run concluded, so the document reports it rather
     * than a line printed after the document could ever say.
     */
    public function covered(CoverageVerdict $verdict): void
    {
        $this->coverage = $verdict;
    }

    /**
     * Takes what stopped the run: a missing bootstrap, a rejected option, an
     * error that killed the process. The first one to arrive is the one that
     * mattered, so it is not overwritten by whatever came apart afterwards.
     *
     * @param string $message what went wrong
     * @param string|null $at where, as a project-relative file:line
     */
    public function stopped(string $message, ?string $at = null): void
    {
        $this->fatal ??= ['message' => $message, 'at' => $at];
    }

    /**
     * The process is going. Whatever ended it becomes part of the document, and
     * the document goes out with what the run managed to collect.
     */
    private function ended(?Fatal $fatal): void
    {
        if ($fatal !== null) {
            $this->stopped($fatal->message, $this->location($fatal->file, $fatal->line));
        }

        $this->publish();
    }

    /**
     * A whole-suite render is complete in itself (a report file, a spec), so it
     * publishes as it ends.
     */
    public function format(SuiteResult $results): void
    {
        parent::format($results);
        $this->publish();
    }

    /**
     * Writes the document, once. Every path out of the command comes through
     * here, so an agent reads exactly one JSON object whether the run finished,
     * failed its coverage gate, or died: calling it twice is a no-op.
     */
    public function publish(): void
    {
        if ($this->published) {
            return;
        }

        $this->published = true;
        // The zero fraction is kept: a float that lost it would read as the int
        // it was compared against, turning a type failure into a tautology.
        $json = json_encode($this->document(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';

        $this->output->write($json . "\n", false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * The document as it stands: the header, every actionable entry, and the
     * totals. Built from whatever has been collected, so a run cut short still
     * reports what it managed to see.
     *
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $failing = $this->counts['failing'] ?? 0;
        $errors = $this->counts['error'] ?? 0;
        $pending = $this->counts['pending'] ?? 0;
        // A coverage gate the run missed is work left to do, exactly like a red
        // example: the number an agent checks must not say "nothing to do" while
        // the exit code says otherwise.
        $shortfall = $this->coverage !== null && !$this->coverage->met() ? 1 : 0;
        $stopped = $this->fatal !== null ? 1 : 0;

        $result = [
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
                // (failures + errors + pending), plus a missed coverage gate and
                // anything that stopped the run. Zero means nothing to do.
                'actionable' => $failing + $errors + $pending + $shortfall + $stopped,
                'duration_ms' => (int) round(($this->results?->getDuration() ?? 0.0) * 1000),
                'offers' => $this->results !== null ? $this->offers($this->results) : [],
            ],
        ];

        $rerun = $this->rerunEverything();
        if ($rerun !== null) {
            $result['result']['rerun'] = $rerun;
        }

        if ($this->coverage !== null) {
            $result['result']['coverage'] = [
                'percent' => round($this->coverage->percent, 1),
                'required' => $this->coverage->required,
                'met' => $this->coverage->met(),
            ];
        }

        if ($this->fatal !== null) {
            $result['fatal'] = $this->fatal;
        }

        return $result;
    }

    /**
     * The one command that re-runs everything this run reported, so a fix can be
     * checked against the whole of what it was meant to fix instead of one
     * example at a time. Null when nothing failed anywhere with a location.
     */
    private function rerunEverything(): ?string
    {
        $targets = [];

        foreach ($this->examples as $entry) {
            $rerun = $entry['rerun'] ?? null;
            if (is_string($rerun)) {
                $targets[] = substr($rerun, strlen('run '));
            }
        }

        $targets = array_values(array_unique($targets));

        return $targets !== [] ? 'run ' . implode(' ', $targets) : null;
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
     * The origin one level into a result: a spec or feature adds its title (and
     * a feature the file it lives in), a scenario adds its title and the line
     * that re-runs it. Anything else passes the origin through untouched.
     */
    private function within(Origin $origin, Results $result): Origin
    {
        return match (true) {
            $result instanceof FeatureResult => $origin->within($result->getTitle(), $result->getPath()),
            $result instanceof ScenarioResult => $origin->within($result->getTitle(), line: $result->getLine()),
            $result instanceof SpecificationResult => $origin->within($result->getTitle()),
            default => $origin,
        };
    }

    /**
     * Walks the result tree, emitting one entry per example/step and threading
     * the origin down so each entry is named in full and knows where it lives.
     */
    private function collect(Results $results, Origin $origin): void
    {
        foreach ($results->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                $this->record($this->fromExample($child, $origin), 'example');
            } elseif ($child instanceof StepResult) {
                $this->record($this->fromStep($child, $origin), 'step');
            } elseif ($child instanceof Results) {
                $this->collect($child, $this->within($origin, $child));
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
    private function fromExample(ExampleResult $example, Origin $origin): array
    {
        $state = $this->exampleState($example);
        $name = $origin->name($example->getTitle());
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
            // Mirrored, so one field answers "what went wrong" whatever the
            // state: the exception adds the class and the site, not the text.
            $entry['message'] = $error?->getMessage();
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
    private function fromStep(StepResult $step, Origin $origin): array
    {
        $state = match (true) {
            $step->isPending() || $step->isUndefined() => 'pending',
            $step->isFailure() || $step->isError() => 'failing',
            $step->isSkipped() => 'skipped',
            default => 'passing',
        };

        $name = $origin->name($step->getTitle());
        $entry = [
            'v' => Schema::V,
            'id' => $this->identify($name),
            'example' => $name,
            'state' => $state,
        ];

        if ($state === 'failing' && $step->getError() !== null) {
            $entry['message'] = $step->getError()->getMessage();
        }

        // A scenario is addressed by the line its keyword sits on, which is what
        // "file.feature:LINE" already selects, so a failing scenario re-runs on
        // its own instead of dragging the whole story suite with it.
        $location = $this->location($origin->path, $origin->line);
        if ($state !== 'passing' && $location !== null) {
            $entry['spec'] = $location;
            $this->addRerun($entry, $location);
        }

        return $entry;
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
