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
use PhpSpec\Guard\Verdict as GuardVerdict;
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
 * Speaks the run to a coding agent in JSON Lines: a run_started header, one
 * event per example or scenario that needs attention as it happens, and a
 * summary with the totals. No ANSI, no prose, and no waiting for the end of a
 * long suite — the failure itself is the payload, delivered while the rest is
 * still running.
 */
final class Agent extends AbstractFormatter
{
    /** @var list<string> what each reported entry re-runs, for the summary's one command */
    private array $rerunTargets = [];

    /** @var array<string, int> tally of every unit by state, including passing */
    private array $counts = [];

    /** How many spec examples ran (as opposed to story steps). */
    private int $exampleCount = 0;

    /** How many story (feature) steps ran. */
    private int $stepCount = 0;

    /** How many story scenarios ran: the unit a story run is counted and reported in. */
    private int $scenarioCount = 0;

    /** @var (\Closure(SuiteResult): mixed)|null resolves the run's generation candidates as a plain array */
    private readonly ?\Closure $resolveCandidates;

    /** The randomisation seed of this run, when order is random. */
    private ?int $seed = null;

    /** What was run, as the paths the loader was given. */
    private string $suite = 'default';

    /** The run's results, once it reached the end; null while it is still going. */
    private ?SuiteResult $results = null;

    /** Whether the header has gone out, so it leads the stream exactly once. */
    private bool $started = false;

    /** Whether the summary has gone out, so it closes the stream exactly once. */
    private bool $published = false;

    /** What the run covered, when coverage was collected. */
    private ?CoverageVerdict $coverage = null;

    /** What guard made of the change, when guard is on. */
    private ?GuardVerdict $guard = null;

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

    public function begin(): void
    {
        $this->start();
    }

    /**
     * Opens the stream with the header, once. Every event goes through a caller
     * that has started it first, so the header leads the output even when the
     * run died before it ever began.
     */
    private function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;
        $this->emit([
            'v' => Schema::V,
            'event' => Schema::EVENT_RUN_STARTED,
            'suite' => $this->suite,
            'seed' => $this->seed,
        ]);
    }

    public function printResult(Results $result): void
    {
        $origin = $this->within(new Origin(), $result);

        if ($result instanceof ScenarioResult) {
            $this->recordScenario($result, $origin);

            return;
        }

        $this->collect($result, $origin);
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
     * Takes what guard concluded, so a reader gets {member, lines, remedy} as
     * data on the same channel as everything else instead of parsing a wall of
     * red text meant for a person.
     */
    public function guarded(GuardVerdict $verdict): void
    {
        $this->guard = $verdict;
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
     * Closes the stream, once. Every path out of the command comes through
     * here, so the last line an agent reads is always the summary whether the
     * run finished, failed its coverage gate, or died: calling it twice is a
     * no-op.
     */
    public function publish(): void
    {
        if ($this->published) {
            return;
        }

        $this->published = true;
        $this->start();

        if ($this->fatal !== null) {
            $this->emit([
                'v' => Schema::V,
                'event' => Schema::EVENT_FATAL,
                'message' => $this->fatal['message'],
                'at' => $this->fatal['at'],
            ]);
        }

        $this->emit($this->summary());
    }

    /**
     * Writes one event on a line of its own, which is the whole of the wire
     * format: a reader decodes a line and acts on it, without waiting for the
     * run to end.
     *
     * @param array<string, mixed> $event
     */
    private function emit(array $event): void
    {
        // The zero fraction is kept: a float that lost it would read as the int
        // it was compared against, turning a type failure into a tautology.
        $json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '{}';

        $this->output->write($json . "\n", false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * The closing event: the totals, in the units the entries were reported in.
     * Built from whatever has been collected, so a run cut short still reports
     * what it managed to see.
     *
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $failing = $this->counts['failing'] ?? 0;
        $errors = $this->counts['error'] ?? 0;
        $pending = $this->counts['pending'] ?? 0;
        // A coverage gate the run missed is work left to do, exactly like a red
        // example: the number an agent checks must not say "nothing to do" while
        // the exit code says otherwise.
        $shortfall = $this->coverage !== null && !$this->coverage->met() ? 1 : 0;
        $stopped = $this->fatal !== null ? 1 : 0;
        // Untested logic is work left to do in exactly the sense actionable
        // counts: the run failed and something has to be written.
        $unguarded = $this->guard !== null && !$this->guard->held() ? count($this->guard->violations()) : 0;

        $summary = [
            'v' => Schema::V,
            'event' => Schema::EVENT_SUMMARY,
            'examples' => $this->exampleCount,
            'scenarios' => $this->scenarioCount,
            'steps' => $this->stepCount,
            // Counted in the units the entries are reported in: one per
            // example, one per scenario. Steps are a size, not a verdict.
            'passing' => $this->counts['passing'] ?? 0,
            'failing' => $failing,
            'errors' => $errors,
            'pending' => $pending,
            'skipped' => $this->counts['skipped'] ?? 0,
            // The one number an agent checks: everything red or unfinished
            // (failures + errors + pending), plus a missed coverage gate and
            // anything that stopped the run. Zero means nothing to do.
            'actionable' => $failing + $errors + $pending + $shortfall + $stopped + $unguarded,
            'duration_ms' => (int) round(($this->results?->getDuration() ?? 0.0) * 1000),
        ];

        $rerun = $this->rerunEverything();
        if ($rerun !== null) {
            $summary['rerun'] = $rerun;
        }

        if ($this->guard !== null && !$this->guard->held()) {
            $summary['guard'] = $this->guard->toArray();
        }

        if ($this->coverage !== null) {
            $summary['coverage'] = [
                'percent' => round($this->coverage->percent, 1),
                'required' => $this->coverage->required,
                'met' => $this->coverage->met(),
            ];
        }

        // Present only when there is something to take, as with everything else
        // the summary carries conditionally: an empty list reads as a promise
        // that was never made.
        $offers = $this->results !== null ? $this->offers($this->results) : [];
        if ($offers !== []) {
            $summary['offers'] = $offers;
        }

        return $summary;
    }

    /**
     * The one command that re-runs everything this run reported, so a fix can be
     * checked against the whole of what it was meant to fix instead of one
     * example at a time. Null when nothing failed anywhere with a location.
     */
    private function rerunEverything(): ?string
    {
        $targets = array_values(array_unique($this->rerunTargets));

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
                $this->record($this->fromExample($child, $origin));
            } elseif ($child instanceof ScenarioResult) {
                // A scenario is the unit a story run reports: it is what fails,
                // what re-runs, and what a reader acts on. Its steps ride inside
                // it rather than becoming entries of their own, so one broken
                // scenario is one thing to fix and not a failure plus a train of
                // skipped siblings that address the same line.
                $this->recordScenario($child, $this->within($origin, $child));
            } elseif ($child instanceof Results) {
                $this->collect($child, $this->within($origin, $child));
            }
        }
    }

    /**
     * Counts an example by state, and reports it unless it passed: a green
     * suite of thousands need not spend tokens on entries an agent will never
     * act on; the summary still counts them.
     *
     * @param array<string, mixed> $entry
     */
    private function record(array $entry): void
    {
        $state = is_string($entry['state'] ?? null) ? $entry['state'] : 'passing';
        $this->counts[$state] = ($this->counts[$state] ?? 0) + 1;
        $this->exampleCount++;

        if ($state !== 'passing') {
            $this->report($entry);
        }
    }

    /**
     * Sends an entry out the moment it is known, and remembers what it re-runs
     * so the summary can still hand over one command for the lot.
     *
     * @param array<string, mixed> $entry
     */
    private function report(array $entry): void
    {
        $this->start();

        $rerun = $entry['rerun'] ?? null;
        if (is_string($rerun)) {
            $this->rerunTargets[] = substr($rerun, strlen('run '));
        }

        $this->emit($entry);
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
            'event' => Schema::EVENT_EXAMPLE,
            'id' => $this->identify($name),
            'example' => $name,
            'state' => $state,
        ];

        if ($state === 'failing') {
            $match = $this->failingMatch($example);
            $entry += $this->expectation($match);
            $entry['message'] = $example->getMessage();
            $this->addLocation($entry, $this->location($match?->getFile(), $match?->getLine()));
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
            $this->addLocation($entry, $this->location($error?->getFile(), $error?->getLine()));
            // A missing class/method/interface the error names becomes a concrete
            // offer to generate it, right on the example that hit it. Only present
            // when the error actually maps to something a generator can create.
            $offer = Offers::forError($error?->getMessage() ?? '');
            if ($offer !== null) {
                $entry['offer'] = $offer;
            }
        }

        $this->attachOutput($entry, $example->getOutput());
        $this->attachHandedOver($entry, $example->getAttachments());
        $this->attachNotes($entry, $example);

        return $entry;
    }

    /**
     * Attaches what the spec or scenario handed over about itself: the log it
     * watched, the output of a process it drove, the page a browser was
     * showing. PhpSpec cannot reach any of that on its own, so what is here was
     * offered deliberately, and only for an entry worth diagnosing.
     *
     * @param array<string, mixed> $entry
     * @param array<string, string|array{error: string}> $attachments
     */
    private function attachHandedOver(array &$entry, array $attachments): void
    {
        $reported = [];

        foreach ($attachments as $name => $value) {
            $reported[$name] = is_string($value) ? ValueExporter::exportOutput($value) : $value;
        }

        if ($reported !== []) {
            $entry['attachments'] = $reported;
        }
    }

    /**
     * Attaches what the subject printed while the entry ran, when it printed
     * anything. A process a step drove, a dump left in a spec: whatever reached
     * standard output is a diagnosis about this entry, and it is reported here
     * rather than mixed into the stream the document is written on.
     *
     * @param array<string, mixed> $entry
     */
    private function attachOutput(array &$entry, string $printed): void
    {
        if ($printed !== '') {
            $entry['output'] = ValueExporter::exportOutput($printed);
        }
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
     * The expectation that did not hold, as the two values a reader compares.
     * Whatever failed, an example or a story step, it is reported the same way,
     * so nobody has to read a sentence back to find out what was wanted.
     *
     * phpspec names these from the matcher's point of view: getExpected() is the
     * expect() subject (the value the code produced) and getActual() is the
     * matcher's argument (the target). The agent contract uses the universal
     * convention, so they are swapped here: actual = the subject, expected =
     * the target.
     *
     * A failure that came back without its site came back without its detail:
     * a parallel worker reports through JUnit, which carries the message and
     * nothing else, and its stand-in expectation compares null with null. Only
     * the site tells that apart from a real failure whose values are null,
     * which is a comparison worth reporting: an anonymous matcher (any
     * __call-based custom or predicate matcher) has no name to give either.
     *
     * @return array{expectation?: array{matcher: string|null, expected: mixed, actual: mixed, negated: bool}}
     */
    private function expectation(?MatchResult $match): array
    {
        if ($match === null || $this->location($match->getFile(), $match->getLine()) === null) {
            return [];
        }

        return [
            'expectation' => [
                'matcher' => $match->getMatcher(),
                'expected' => ValueExporter::export($match->getActual()),
                'actual' => ValueExporter::export($match->getExpected()),
                'negated' => $match->isNegated(),
            ],
        ];
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
    /**
     * Records one scenario: counted whatever it did, and emitted when it needs
     * attention, carrying the steps that were not passing so the reader sees
     * which one broke (or which are still undefined) without a second lookup.
     */
    private function recordScenario(ScenarioResult $scenario, Origin $origin): void
    {
        $steps = [];
        $state = 'passing';
        $message = null;
        $expectation = [];
        $printed = '';

        foreach ($scenario->getResults() as $step) {
            if (!$step instanceof StepResult) {
                continue;
            }

            $this->stepCount++;
            $stepState = $this->stepState($step);
            // Every step's, passing ones included: a scenario that shells out
            // usually does it in a step that worked, and reads the result in the
            // one that broke.
            $printed .= $step->getOutput();

            if ($stepState === 'passing') {
                continue;
            }

            $reported = ['title' => $step->getTitle(), 'state' => $stepState];

            if ($stepState === 'failing' && $step->getError() !== null) {
                $reported['message'] = $step->getError()->getMessage();
                $message ??= $step->getError()->getMessage();
            }

            // An expectation that did not hold puts its two values on the step,
            // and on the scenario alongside the message it already hoists, so a
            // reader acting on the entry never has to go a level deeper.
            $match = $step->getMatch();
            if ($match !== null) {
                $reported += $this->expectation($match);
                $at = $this->location($match->getFile(), $match->getLine());
                if ($at !== null) {
                    $reported['at'] = $at;
                }
                $expectation = $expectation !== [] ? $expectation : $this->expectation($match);
            }

            $steps[] = $reported;
            $state = self::worst($state, $stepState);
        }

        $this->scenarioCount++;
        $this->counts[$state] = ($this->counts[$state] ?? 0) + 1;

        if ($state === 'passing') {
            return;
        }

        $entry = [
            'v' => Schema::V,
            'event' => Schema::EVENT_EXAMPLE,
            'id' => $this->identify($origin->name),
            'example' => $origin->name,
            'state' => $state,
        ];

        $entry += $expectation;

        if ($message !== null) {
            $entry['message'] = $message;
        }

        $this->attachOutput($entry, $printed);
        $this->attachHandedOver($entry, $scenario->getAttachments());
        $entry['steps'] = $steps;

        // A scenario is addressed by the line its keyword sits on, which is what
        // "file.feature:LINE" already selects, so a failing scenario re-runs on
        // its own instead of dragging the whole story suite with it. Only a
        // failure is addressed, as with examples: a scenario waiting on undefined
        // steps is work to write, not work to re-run.
        if ($state === 'failing') {
            $this->addLocation($entry, $this->location($origin->path, $origin->line));
        }

        $this->report($entry);
    }

    /**
     * A step's state in the document's vocabulary.
     */
    private function stepState(StepResult $step): string
    {
        return match (true) {
            $step->isPending() || $step->isUndefined() => 'pending',
            $step->isFailure() || $step->isError() => 'failing',
            $step->isSkipped() => 'skipped',
            default => 'passing',
        };
    }

    /**
     * The state a scenario takes from its steps: the one that most needs
     * attention wins, so a scenario whose first step failed reads as failing
     * however many of its steps were skipped behind it.
     */
    private static function worst(string $state, string $candidate): string
    {
        $order = ['passing' => 0, 'skipped' => 1, 'pending' => 2, 'failing' => 3];

        return $order[$candidate] > $order[$state] ? $candidate : $state;
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
     * Attaches where the entry failed, and the exact line-targeted command that
     * re-runs just that one, so an agent can verify a single fix without a
     * full-suite run. phpspec resolves a "spec.php:LINE" path to the example
     * whose closure spans that line, and the expectation/error site always
     * falls inside it, so the entry's own location is a valid target. Both are
     * absent when the location is not known: a key that says null is a question
     * a reader has to ask twice.
     *
     * @param array<string, mixed> $entry
     */
    private function addLocation(array &$entry, ?string $location): void
    {
        if ($location !== null) {
            $entry['spec'] = $location;
            $entry['rerun'] = 'run ' . $location;
        }
    }

    /**
     * Renders a file:line as a project-relative, forward-slashed location, or
     * null when either part is missing. A blank file or a line of zero is
     * missing too: a result that came back without its site (a parallel worker
     * reports through JUnit, which carries none) must not be dressed up as
     * ":0", which reads like a location and re-runs like nonsense.
     */
    private function location(?string $file, ?int $line): ?string
    {
        if ($file === null || $line === null || $file === '' || $line <= 0) {
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
