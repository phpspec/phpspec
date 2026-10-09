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

namespace PhpSpec\Parallel;

use PhpSpec\ObjectName;
use PhpSpec\Report\ReportedObject;
use PhpSpec\Report\Typed;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;

/**
 * @internal
 * The line a parallel worker reports a result on, and the result read back
 * off it. Every result crosses whole: a failure with both values, the matcher
 * and the site; an error with its type, its site and the frames of the user's
 * code; what an example printed and handed over; where it was declared. A
 * value JSON has no word for crosses as a stand-in: an object by its name and
 * the way it is shown, a float that is not a number by its name.
 */
final class Wire
{
    private const VERSION = 1;
    private const OBJECT = '@phpspec:object';
    private const FLOAT = '@phpspec:float';
    private const DEPTH_MAX = 64;

    public function encode(SpecificationResult|FeatureResult $result): string
    {
        $line = ['wire' => self::VERSION] + ($result instanceof FeatureResult
            ? ['feature' => $this->feature($result)]
            : ['spec' => $this->specification($result)]);

        return json_encode($line, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * The line that says the worker reported everything it ran.
     */
    public function ending(): string
    {
        return json_encode(['wire' => self::VERSION, 'end' => true], JSON_THROW_ON_ERROR);
    }

    /**
     * The result a line carries; true for the line that ends the report; null
     * for a line that is not on the wire, such as what a bootstrap printed.
     */
    public function decode(string $line): SpecificationResult|FeatureResult|true|null
    {
        if (!str_starts_with($line, '{')) {
            return null;
        }

        $data = json_decode($line, true);

        if (!is_array($data) || ($data['wire'] ?? null) !== self::VERSION) {
            return null;
        }

        if (isset($data['end'])) {
            return true;
        }

        if (is_array($data['feature'] ?? null)) {
            return $this->featureFrom($data['feature']);
        }

        if (is_array($data['spec'] ?? null)) {
            return $this->specificationFrom($data['spec']);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function specification(SpecificationResult $spec): array
    {
        return ['title' => $spec->getTitle(), 'path' => $spec->getPath(), 'results' => $this->nodes($spec->getResults())];
    }

    /**
     * @param array<int, mixed> $results
     * @return list<array<string, mixed>>
     */
    private function nodes(array $results): array
    {
        $nodes = [];

        foreach ($results as $result) {
            if ($result instanceof ContextResult) {
                $nodes[] = $this->context($result);
            } elseif ($result instanceof ExampleResult) {
                $nodes[] = $this->example($result);
            }
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(ContextResult $context): array
    {
        $node = ['context' => $context->getTitle(), 'results' => $this->nodes($context->getResults())];

        if ($context->isError() && $context->getError() instanceof ExampleError) {
            $node['error'] = $this->error($context->getError());
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function example(ExampleResult $example): array
    {
        $node = [
            'example' => $example->getTitle(),
            'matches' => array_map($this->match(...), $example->getResults()),
            'errored' => $example->isError(),
            'pending' => $example->isPending(),
            'skipped' => $example->isSkipped(),
            'risky' => $example->isRisky(),
            'reason' => $example->getReason(),
            'file' => $example->getFile(),
            'line' => $example->getLine(),
            'duration' => $example->getDuration(),
            'output' => $example->getOutput(),
            'attachments' => $example->getAttachments(),
            'warnings' => $example->getWarnings(),
            'deprecations' => $example->getDeprecations(),
            'notices' => $example->getNotices(),
        ];

        if ($example->getError() !== null) {
            $node['error'] = $this->error($example->getError());
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function match(MatchResult $match): array
    {
        if (!$match->isFailure()) {
            return ['passed' => true];
        }

        return [
            'passed' => false,
            'expected' => $this->value($match->getExpected()),
            'actual' => $this->value($match->getActual()),
            'message' => (string) $match->getMessage(),
            'file' => $match->getFile() ?? '',
            'line' => $match->getLine() ?? 0,
            'fake' => $match->getFakeExpression(),
            'matcher' => $match->getMatcher(),
            'negated' => $match->isNegated(),
            'implied' => $match->isTargetImplied(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(ExampleError|StepError $error): array
    {
        return [
            'message' => $error->getMessage(),
            'type' => $error->getType(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => array_map(
                static fn(array $frame): array => array_intersect_key($frame, ['file' => true, 'line' => true, 'function' => true, 'class' => true]),
                $error->getFilteredTrace(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feature(FeatureResult $feature): array
    {
        $scenarios = [];

        foreach ($feature->getResults() as $scenario) {
            if ($scenario instanceof ScenarioResult) {
                $scenarios[] = $this->scenario($scenario);
            }
        }

        return ['title' => $feature->getTitle(), 'path' => $feature->getPath(), 'scenarios' => $scenarios];
    }

    /**
     * @return array<string, mixed>
     */
    private function scenario(ScenarioResult $scenario): array
    {
        $steps = [];

        foreach ($scenario->getResults() as $step) {
            if ($step instanceof StepResult) {
                $steps[] = $this->step($step);
            }
        }

        return [
            'title' => $scenario->getTitle(),
            'line' => $scenario->getLine(),
            'attachments' => $scenario->getAttachments(),
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function step(StepResult $step): array
    {
        $node = [
            'step' => $step->getTitle(),
            'state' => $step->getState(),
            'reason' => $step->getReason(),
            'warnings' => $step->getWarnings(),
            'output' => $step->getOutput(),
            'duration' => $step->getDuration(),
        ];

        if ($step->getError() !== null) {
            $node['error'] = $this->error($step->getError());
        }

        if ($step->getMatch() !== null) {
            $node['match'] = $this->match($step->getMatch());
        }

        return $node;
    }

    /**
     * A compared value as JSON can carry it: a scalar or an array as it is, an
     * object as its name and the way it is shown, a float that is not a number
     * by its name, an array too deep to cross as the way it is shown.
     */
    private function value(mixed $value, int $depth = 0): mixed
    {
        if (is_object($value) || is_resource($value)) {
            return [self::OBJECT => [
                'name' => is_object($value) ? ObjectName::of($value) : sprintf('resource (%s)', get_resource_type($value)),
                'shown' => Typed::value($value),
            ]];
        }

        if (is_float($value) && !is_finite($value)) {
            return [self::FLOAT => is_nan($value) ? 'NAN' : ($value > 0 ? 'INF' : '-INF')];
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($depth >= self::DEPTH_MAX) {
            return [self::OBJECT => ['name' => 'array', 'shown' => Typed::value($value)]];
        }

        $encoded = [];
        foreach ($value as $key => $item) {
            $encoded[$key] = $this->value($item, $depth + 1);
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function specificationFrom(array $data): SpecificationResult
    {
        return new SpecificationResult(
            (string) ($data['title'] ?? ''),
            $this->nodesFrom(is_array($data['results'] ?? null) ? $data['results'] : []),
            (string) ($data['path'] ?? ''),
        );
    }

    /**
     * @param array<int, mixed> $nodes
     * @return list<ContextResult|ExampleResult>
     */
    private function nodesFrom(array $nodes): array
    {
        $results = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $results[] = isset($node['context']) ? $this->contextFrom($node) : $this->exampleFrom($node);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function contextFrom(array $node): ContextResult
    {
        $context = new ContextResult((string) $node['context'], $this->nodesFrom(is_array($node['results'] ?? null) ? $node['results'] : []));

        if (is_array($node['error'] ?? null)) {
            $context->setError($this->exampleErrorFrom($node['error']));
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function exampleFrom(array $node): ExampleResult
    {
        $example = new ExampleResult(
            (string) ($node['example'] ?? ''),
            array_map($this->matchFrom(...), is_array($node['matches'] ?? null) ? $node['matches'] : []),
            (bool) ($node['errored'] ?? false),
            (bool) ($node['pending'] ?? false),
            (bool) ($node['skipped'] ?? false),
            isset($node['reason']) ? (string) $node['reason'] : null,
        );

        if (isset($node['file'], $node['line'])) {
            $example->declaredAt((string) $node['file'], (int) $node['line']);
        }

        if ($node['risky'] ?? false) {
            $example->markRisky();
        }

        $example->setDuration((float) ($node['duration'] ?? 0.0));
        $example->setOutput((string) ($node['output'] ?? ''));
        $example->setAttachments(is_array($node['attachments'] ?? null) ? $node['attachments'] : []);
        $example->setWarnings($this->notesFrom($node['warnings'] ?? null));
        $example->setDeprecations($this->notesFrom($node['deprecations'] ?? null));
        $example->setNotices($this->notesFrom($node['notices'] ?? null));

        if (is_array($node['error'] ?? null)) {
            $example->setError($this->exampleErrorFrom($node['error']));
        }

        return $example;
    }

    /**
     * @param array<string, mixed> $match
     */
    private function matchFrom(array $match): MatchResult
    {
        if ($match['passed'] ?? false) {
            return MatchResult::passed();
        }

        return MatchResult::failed(
            $this->valueFrom($match['expected'] ?? null),
            $this->valueFrom($match['actual'] ?? null),
            (string) ($match['message'] ?? ''),
            (string) ($match['file'] ?? ''),
            (int) ($match['line'] ?? 0),
            isset($match['fake']) ? (string) $match['fake'] : null,
            isset($match['matcher']) ? (string) $match['matcher'] : null,
            (bool) ($match['negated'] ?? false),
            (bool) ($match['implied'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $error
     */
    private function exampleErrorFrom(array $error): ExampleError
    {
        return ExampleError::fromReport(...$this->reported($error));
    }

    /**
     * @param array<string, mixed> $error
     */
    private function stepErrorFrom(array $error): StepError
    {
        return StepError::fromReport(...$this->reported($error));
    }

    /**
     * @param array<string, mixed> $error
     * @return array{string, string, string, int, array<int, array<string, mixed>>}
     */
    private function reported(array $error): array
    {
        return [
            (string) ($error['message'] ?? ''),
            (string) ($error['type'] ?? 'Error'),
            (string) ($error['file'] ?? ''),
            (int) ($error['line'] ?? 0),
            is_array($error['trace'] ?? null) ? array_values(array_filter($error['trace'], 'is_array')) : [],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function featureFrom(array $data): FeatureResult
    {
        $scenarios = [];

        foreach (is_array($data['scenarios'] ?? null) ? $data['scenarios'] : [] as $scenario) {
            if (is_array($scenario)) {
                $scenarios[] = $this->scenarioFrom($scenario);
            }
        }

        return new FeatureResult((string) ($data['title'] ?? ''), $scenarios, (string) ($data['path'] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function scenarioFrom(array $data): ScenarioResult
    {
        $steps = [];

        foreach (is_array($data['steps'] ?? null) ? $data['steps'] : [] as $step) {
            if (is_array($step)) {
                $steps[] = $this->stepFrom($step);
            }
        }

        return new ScenarioResult(
            (string) ($data['title'] ?? ''),
            $steps,
            (int) ($data['line'] ?? 0),
            is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
        );
    }

    /**
     * @param array<string, mixed> $node
     */
    private function stepFrom(array $node): StepResult
    {
        $step = new StepResult(
            (string) ($node['step'] ?? ''),
            (string) ($node['state'] ?? 'passed'),
            isset($node['reason']) ? (string) $node['reason'] : null,
        );
        $step->setWarnings($this->notesFrom($node['warnings'] ?? null));
        $step->setOutput((string) ($node['output'] ?? ''));
        $step->setDuration((float) ($node['duration'] ?? 0.0));

        if (is_array($node['error'] ?? null)) {
            $step->setError($this->stepErrorFrom($node['error']));
        }

        if (is_array($node['match'] ?? null)) {
            $step->setMatch($this->matchFrom($node['match']));
        }

        return $step;
    }

    /**
     * The warnings, deprecations or notices a node carried, each one whole.
     *
     * @return list<array{severity: int, message: string, file: string, line: int}>
     */
    private function notesFrom(mixed $notes): array
    {
        $read = [];

        foreach (is_array($notes) ? $notes : [] as $note) {
            if (is_array($note) && isset($note['severity'], $note['message'], $note['file'], $note['line'])) {
                $read[] = [
                    'severity' => (int) $note['severity'],
                    'message' => (string) $note['message'],
                    'file' => (string) $note['file'],
                    'line' => (int) $note['line'],
                ];
            }
        }

        return $read;
    }

    private function valueFrom(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (count($value) === 1 && is_array($value[self::OBJECT] ?? null)) {
            return new ReportedObject((string) ($value[self::OBJECT]['name'] ?? ''), (string) ($value[self::OBJECT]['shown'] ?? ''));
        }

        if (count($value) === 1 && is_string($value[self::FLOAT] ?? null)) {
            return match ($value[self::FLOAT]) {
                'NAN' => NAN,
                'INF' => INF,
                default => -INF,
            };
        }

        $decoded = [];
        foreach ($value as $key => $item) {
            $decoded[$key] = $this->valueFrom($item);
        }

        return $decoded;
    }
}
