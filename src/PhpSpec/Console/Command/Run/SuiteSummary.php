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
     * @param 'red'|'green' $status
     * @param array{examples: int, passes: int, failures: int, errors: int, pending: int} $counts
     * @param list<array{subject: string, example: string}> $failing
     * @param list<array{subject: string, example: string}> $pending
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
     * SpecificationResult, not the ExampleResult, so it is threaded down).
     *
     * @param list<array{subject: string, example: string}> $failing
     * @param list<array{subject: string, example: string}> $pending
     */
    private static function classify(Results $node, string $subject, array &$failing, array &$pending): void
    {
        foreach ($node->getResults() as $child) {
            if ($child instanceof ExampleResult) {
                if ($child->isFailure() || $child->isError()) {
                    $failing[] = ['subject' => $subject, 'example' => $child->getTitle()];
                } elseif ($child->isPending()) {
                    $pending[] = ['subject' => $subject, 'example' => $child->getTitle()];
                }
            } elseif ($child instanceof Results) {
                self::classify($child, $subject, $failing, $pending);
            }
        }
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

    /** @return list<array{subject: string, example: string}> */
    public function failing(): array
    {
        return $this->failing;
    }

    /** @return list<array{subject: string, example: string}> */
    public function pending(): array
    {
        return $this->pending;
    }

    /** @return array{subject: string, example: string}|null */
    public function nearestPendingGap(): ?array
    {
        return $this->pending[0] ?? null;
    }

    /**
     * @return array{status: string, counts: array<string, int>, failing: list<array{subject: string, example: string}>, pending: list<array{subject: string, example: string}>}
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

        /** @var list<array{subject: string, example: string}> $failing */
        $failing = is_array($data['failing'] ?? null) ? array_values($data['failing']) : [];

        /** @var list<array{subject: string, example: string}> $pending */
        $pending = is_array($data['pending'] ?? null) ? array_values($data['pending']) : [];

        return new self(
            ($data['status'] ?? 'green') === 'red' ? 'red' : 'green',
            [
                'examples' => $counts['examples'] ?? 0,
                'passes' => $counts['passes'] ?? 0,
                'failures' => $counts['failures'] ?? 0,
                'errors' => $counts['errors'] ?? 0,
                'pending' => $counts['pending'] ?? 0,
            ],
            $failing,
            $pending,
        );
    }
}
