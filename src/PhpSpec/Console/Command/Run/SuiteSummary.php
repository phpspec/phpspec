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

namespace PhpSpec\Console\Command\Run;

use PhpSpec\Result\Counts;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Results;

/**
 * @internal
 * A compact, serialisable snapshot of a suite run — red/green, counts, and the
 * failing/pending examples with their subjects — carried across the pair-mode
 * subprocess boundary so the navigator can react to real suite state.
 */
final readonly class SuiteSummary
{
    /**
     * The longest an example's error message is carried at; enough to name the
     * expectation that failed without flooding the model's context.
     */
    private const ERROR_MAX = 300;

    /**
     * @param 'red'|'green' $status
     * @param array{examples: int, passes: int, failures: int, errors: int, pending: int} $counts
     * @param list<array{subject: string, example: string, error: string}> $failing
     * @param list<array{subject: string, example: string, error: string}> $pending
     */
    public function __construct(
        private string $status,
        private array $counts = ['examples' => 0, 'passes' => 0, 'failures' => 0, 'errors' => 0, 'pending' => 0],
        private array $failing = [],
        private array $pending = [],
    ) {}

    /**
     * Builds a compact summary from an in-process suite result.
     *
     * @param SuiteResult $result the aggregated suite result
     */
    public static function fromSuiteResult(SuiteResult $result): self
    {
        $c = (new Counts($result))->toArray();
        $counts = [
            'examples' => $c['examples'],
            'passes' => $c['passes'],
            'failures' => $c['failures'],
            'errors' => $c['errors'],
            'pending' => $c['pending'],
        ];

        $failing = [];
        $pending = [];
        foreach ($result->getResults() as $node) {
            $subject = $node instanceof SpecificationResult ? $node->getTitle() : '';
            self::classify($node, $subject, $failing, $pending);
        }

        return new self($result->status() === 0 ? 'green' : 'red', $counts, $failing, $pending);
    }

    /**
     * Walks a result subtree, collecting failing and pending examples under the
     * subject of the specification they belong to (the subject lives on the
     * SpecificationResult, not the ExampleResult, so it is threaded down). Each
     * failing example carries its error message so the navigator sees *why* it
     * is red, not just that it is.
     *
     * @param list<array{subject: string, example: string, error: string}> $failing
     * @param list<array{subject: string, example: string, error: string}> $pending
     */
    private static function classify(Results $node, string $subject, array &$failing, array &$pending): void
    {
        foreach ($node->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                if ($child->isFailure() || $child->isError()) {
                    $failing[] = [
                        'subject' => $subject,
                        'example' => $child->getTitle(),
                        'error' => self::truncateError($child->getMessage()),
                    ];
                } elseif ($child->isPending()) {
                    $pending[] = ['subject' => $subject, 'example' => $child->getTitle(), 'error' => ''];
                }
            } elseif ($child instanceof Results) {
                self::classify($child, $subject, $failing, $pending);
            }
        }
    }

    /**
     * Collapses an error message to a single line and clips it to ERROR_MAX, so
     * one runaway assertion dump cannot swamp the grounding the model receives.
     */
    private static function truncateError(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        return mb_strlen($message) > self::ERROR_MAX
            ? mb_substr($message, 0, self::ERROR_MAX - 1) . '…'
            : $message;
    }

    /** @return 'red'|'green' */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Whether the suite passed, with no failures or errors.
     */
    public function isGreen(): bool
    {
        return $this->status === 'green';
    }

    /**
     * Whether the suite has any failures or errors.
     */
    public function isRed(): bool
    {
        return $this->status === 'red';
    }

    /**
     * Whether the suite ran no examples at all.
     */
    public function isEmpty(): bool
    {
        return $this->counts['examples'] === 0;
    }

    /** @return array{examples: int, passes: int, failures: int, errors: int, pending: int} */
    public function counts(): array
    {
        return $this->counts;
    }

    /** @return list<array{subject: string, example: string, error: string}> */
    public function failing(): array
    {
        return $this->failing;
    }

    /** @return list<array{subject: string, example: string, error: string}> */
    public function pending(): array
    {
        return $this->pending;
    }

    /** @return array{subject: string, example: string, error: string}|null */
    public function nearestPendingGap(): ?array
    {
        return $this->pending[0] ?? null;
    }

    /**
     * @return array{status: string, counts: array<string, int>, failing: list<array{subject: string, example: string, error: string}>, pending: list<array{subject: string, example: string, error: string}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'counts' => $this->counts,
            'failing' => $this->failing,
            'pending' => $this->pending,
        ];
    }

    /**
     * @param array<string, mixed> $data the decoded summary data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, int> $counts */
        $counts = is_array($data['counts'] ?? null) ? $data['counts'] : [];

        return new self(
            ($data['status'] ?? 'green') === 'red' ? 'red' : 'green',
            [
                'examples' => $counts['examples'] ?? 0,
                'passes' => $counts['passes'] ?? 0,
                'failures' => $counts['failures'] ?? 0,
                'errors' => $counts['errors'] ?? 0,
                'pending' => $counts['pending'] ?? 0,
            ],
            self::normaliseExamples($data['failing'] ?? null),
            self::normaliseExamples($data['pending'] ?? null),
        );
    }

    /**
     * Rebuilds a list of example tuples from decoded data, defaulting any missing
     * field so a child report written before the `error` field existed (a
     * mid-upgrade subprocess) never crashes the parent.
     *
     * @param mixed $items the decoded failing/pending list, of unknown shape
     * @return list<array{subject: string, example: string, error: string}>
     */
    private static function normaliseExamples(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalised = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalised[] = [
                'subject' => is_string($item['subject'] ?? null) ? $item['subject'] : '',
                'example' => is_string($item['example'] ?? null) ? $item['example'] : '',
                'error' => is_string($item['error'] ?? null) ? $item['error'] : '',
            ];
        }

        return $normalised;
    }
}
