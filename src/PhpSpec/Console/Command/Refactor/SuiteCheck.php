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
 * or not, and every failure with the spec file it sits in, when that is
 * known. A run that died, or answered with no stream at all, is red and
 * places nothing.
 */
final readonly class SuiteCheck
{
    /**
     * @param list<array{file: string|null, line: string}> $failures each failure, the spec file it sits in when known
     */
    private function __construct(private bool $green, private array $failures) {}

    /**
     * @param string $stream the agent JSON Lines the run printed
     * @param string $errors what it wrote to the error stream
     */
    public static function fromStream(string $stream, string $errors = ''): self
    {
        $failures = [];
        $actionable = null;

        foreach (explode("\n", $stream) as $line) {
            $event = json_decode($line, true);
            if (!is_array($event)) {
                continue;
            }

            match ($event['event'] ?? null) {
                'example' => $failures[] = self::failure($event),
                'fatal' => $failures[] = ['file' => null, 'line' => (string) ($event['message'] ?? 'The run stopped.')],
                'summary' => $actionable = (int) ($event['actionable'] ?? 1),
                default => null,
            };
        }

        if ($actionable === null) {
            return new self(false, [['file' => null, 'line' => trim($errors) !== '' ? trim($errors) : 'The spec run printed no summary.']]);
        }

        return new self($actionable === 0 && $failures === [], $failures);
    }

    public function green(): bool
    {
        return $this->green;
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
     * Each failure on a line of its own: where it is, which example, and what
     * it said.
     */
    public function report(): string
    {
        return implode("\n", array_column($this->failures, 'line'));
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
