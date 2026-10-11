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

namespace PhpSpec\Console\Command\Refactor;

/**
 * @internal
 * What a run of the whole spec suite said, read from its agent stream: green
 * when it left nothing to act on, and otherwise everything it left, each with
 * the spec file it sits in when there is one. A run that died, or answered
 * with no stream at all, is red and places nothing.
 */
final readonly class SuiteCheck
{
    private const ACTIONABLE = ['failing', 'error', 'pending'];

    /**
     * @param list<array{file: string|null, line: string}> $failures each failure, the spec file it sits in when known
     */
    private function __construct(private bool $green, private array $failures, private int $ran = 0) {}

    /**
     * @param string $stream the agent JSON Lines the run printed
     * @param string $errors what it wrote to the error stream
     */
    public static function fromStream(string $stream, string $errors = ''): self
    {
        $failures = [];
        $actionable = null;
        $ran = 0;

        foreach (explode("\n", $stream) as $line) {
            $event = json_decode($line, true);
            if (!is_array($event)) {
                continue;
            }

            $failures = [...$failures, ...match ($event['event'] ?? null) {
                'example' => in_array($event['state'] ?? null, self::ACTIONABLE, true) ? [self::failure($event)] : [],
                'fatal' => [['file' => null, 'line' => (string) ($event['message'] ?? 'The run stopped.')]],
                'summary' => self::leftOver($event),
                default => [],
            }];

            if (($event['event'] ?? null) === 'summary') {
                $actionable = (int) ($event['actionable'] ?? 1);
                $ran = (int) ($event['examples'] ?? 0) - (int) ($event['skipped'] ?? 0) - (int) ($event['pending'] ?? 0);
            }
        }

        if ($actionable === null) {
            return new self(false, [['file' => null, 'line' => trim($errors) !== '' ? trim($errors) : 'The spec run printed no summary.']]);
        }

        return new self($actionable === 0, $failures, $ran);
    }

    public function green(): bool
    {
        return $this->green;
    }

    /**
     * How many examples ran: neither skipped nor left pending.
     */
    public function ran(): int
    {
        return $this->ran;
    }

    /**
     * Whether every failure sits in one of these spec files: true when there
     * is none, false when one cannot be placed.
     *
     * @param list<string> $specFiles project-relative paths
     */
    public function confinedTo(array $specFiles): bool
    {
        foreach ($this->failures as $failure) {
            if ($failure['file'] === null || !in_array($failure['file'], $specFiles, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Each failure as a line: where it is, which example, and what it said.
     *
     * @return list<string>
     */
    public function failures(): array
    {
        return array_column($this->failures, 'line');
    }

    /**
     * What the summary still asks for beyond its entries: logic guard found
     * no example reaching, and a coverage gate missed.
     *
     * @param array<string, mixed> $summary
     * @return list<array{file: null, line: string}>
     */
    private static function leftOver(array $summary): array
    {
        $left = [];

        foreach ($summary['guard']['violations'] ?? [] as $violation) {
            $left[] = ['file' => null, 'line' => sprintf('%s:%d  %s', $violation['file'] ?? '', $violation['lines'][0] ?? 0, $violation['remedy'] ?? '')];
        }

        if (($summary['coverage']['met'] ?? true) === false) {
            $left[] = ['file' => null, 'line' => sprintf('Coverage is %s%%, under the %s%% required.', $summary['coverage']['percent'] ?? 0, $summary['coverage']['required'] ?? 0)];
        }

        return $left;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{file: string|null, line: string}
     */
    private static function failure(array $entry): array
    {
        $spec = is_string($entry['spec'] ?? null) ? $entry['spec'] : null;
        $example = (string) ($entry['example'] ?? '');
        $said = is_string($entry['message'] ?? null) ? $entry['message'] : (string) ($entry['state'] ?? '');

        return [
            'file' => $spec === null ? null : (string) preg_replace('/:\d+$/', '', $spec),
            'line' => ($spec === null ? '' : $spec . '  ') . ($example === '' ? '' : $example . ': ') . $said,
        ];
    }
}
