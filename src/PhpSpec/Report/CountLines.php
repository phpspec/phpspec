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

namespace PhpSpec\Report;

/**
 * @internal
 * The closing count of a run in words: a line for the stories when any ran,
 * a line for the specs when any ran, each outcome a part with the tone it is
 * shown in. The console and the HTML report both say it, so they say it alike.
 */
final readonly class CountLines
{
    private const STEP_OUTCOMES = [
        'stepPasses' => ['passed', 'passed'],
        'stepFailures' => ['failed', 'failed'],
        'stepErrors' => ['errored', 'failed'],
        'stepPending' => ['pending', 'warned'],
        'undefined' => ['undefined', 'undefined'],
        'skipped' => ['skipped', 'skipped'],
    ];

    private const EXAMPLE_OUTCOMES = [
        'passes' => ['passed', 'passed'],
        'risky' => ['risky', 'warned'],
        'failures' => ['failed', 'failed'],
        'errors' => ['errored', 'failed'],
        'pending' => ['pending', 'warned'],
        'exampleSkipped' => ['skipped', 'skipped'],
    ];

    /**
     * @param array<string, int> $counts as {@see \PhpSpec\Result\Counts} tallies them
     */
    public function __construct(private array $counts) {}

    /**
     * @return list<array{heading: string, parts: list<array{text: string, tone: string}>}>
     */
    public function lines(): array
    {
        $lines = [];

        if ($this->count('features') > 0) {
            $lines[] = [
                'heading' => implode(', ', [$this->counted('features', 'feature'), $this->counted('scenarios', 'scenario'), $this->counted('steps', 'step')]),
                'parts' => [...$this->outcomes(self::STEP_OUTCOMES), ...$this->notes('stepWarnings', 'stepDeprecations', 'stepNotices')],
            ];
        }

        $specs = $this->count('specs') > 0 ? $this->counted('specs', 'spec') : null;

        if ($this->count('examples') > 0) {
            $lines[] = [
                'heading' => ($specs === null ? '' : $specs . ', ') . $this->counted('examples', 'example'),
                'parts' => [...$this->outcomes(self::EXAMPLE_OUTCOMES), ...$this->notes('warnings', 'deprecations', 'notices')],
            ];
        } elseif ($specs !== null) {
            $lines[] = ['heading' => $specs, 'parts' => []];
        }

        return $lines;
    }

    /**
     * @param array<string, array{0: string, 1: string}> $outcomes the count's key, to its word and tone
     * @return list<array{text: string, tone: string}>
     */
    private function outcomes(array $outcomes): array
    {
        $parts = [];
        foreach ($outcomes as $key => [$word, $tone]) {
            if ($this->count($key) > 0) {
                $parts[] = ['text' => $this->count($key) . ' ' . $word, 'tone' => $tone];
            }
        }

        return $parts;
    }

    /**
     * @return list<array{text: string, tone: string}>
     */
    private function notes(string $warnings, string $deprecations, string $notices): array
    {
        $parts = [];
        foreach ([$warnings => 'warning', $deprecations => 'deprecation', $notices => 'notice'] as $key => $noun) {
            if ($this->count($key) > 0) {
                $parts[] = ['text' => $this->counted($key, $noun), 'tone' => 'warned'];
            }
        }

        return $parts;
    }

    private function counted(string $key, string $noun): string
    {
        return $this->count($key) . ' ' . $noun . ($this->count($key) !== 1 ? 's' : '');
    }

    private function count(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }
}
