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

namespace PhpSpec\Coverage;

use PhpSpec\ByReturnValue;
use RuntimeException;

/**
 * @internal
 * Renders a machine-readable JSON code coverage report designed for mutation
 * testing tools such as Infection. The document maps each source line to the
 * examples that cover it, and records per-example timing, memory usage and
 * source/spec file checksums. The schema is experimental (version 1).
 */
final class JsonReport
{
    private const FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

    /**
     * Renders the coverage data as a version 1 JSON document and writes it
     * to the specified file path. Creates parent directories if they do not exist.
     *
     * The document is written as its sources come, an array of plain values
     * at a time, never held whole, to a file beside the report that takes its
     * place once complete.
     *
     * @param array<string, array{time: float, memory: int, spec_file: string, spec_checksum: string}> $tests per-example metadata keyed by test identifier
     * @param iterable<string, array{checksum: string, lines: array<int, array<int, string>>, executable?: list<int>, methods?: array<string, array{start: int, end: int}>}> $sources per-file checksums, line-to-test-identifier coverage and method spans, keyed by relative file path, each written as it comes
     * @param string $filePath the absolute path to write the JSON file to
     * @throws RuntimeException when the document cannot be encoded as JSON, or written
     */
    public function render(array $tests, iterable $sources, string $filePath): void
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $partial = $dir . '/.' . basename($filePath) . '.' . bin2hex(random_bytes(8));
        $handle = (new ByReturnValue())->call(static fn(): mixed => fopen($partial, 'x'));

        if ($handle === false) {
            throw new RuntimeException(sprintf('The coverage report could not be written to "%s".', $filePath));
        }

        try {
            $this->write($handle, $tests, $sources);
        } catch (RuntimeException $e) {
            fclose($handle);
            unlink($partial);

            throw $e;
        }

        fclose($handle);

        if (is_file($filePath)) {
            chmod($partial, fileperms($filePath) & 0777);
        }

        if (!(new ByReturnValue())->call(static fn(): bool => rename($partial, $filePath))) {
            unlink($partial);

            throw new RuntimeException(sprintf('The coverage report could not be written to "%s".', $filePath));
        }
    }

    /**
     * @param resource $handle
     * @param array<string, mixed> $tests
     * @param iterable<string, mixed> $sources
     */
    private function write($handle, array $tests, iterable $sources): void
    {
        $this->writeValue($handle, ['version' => 1, 'tests' => $tests, 'sources' => $sources], 0);
    }

    /**
     * Writes the value as the whole document's encoding shows it at this
     * depth: one that holds arrays a member at a time, any other at once, so
     * nothing larger than one array of plain values is ever encoded whole. An
     * iterable that is not an array is written with its keys.
     *
     * @param resource $handle
     */
    private function writeValue($handle, mixed $value, int $depth): void
    {
        if (!is_iterable($value) || (is_array($value) && !$this->holdsArrays($value))) {
            $this->put($handle, $this->encoded($value, $depth));

            return;
        }

        $list = is_array($value) && array_is_list($value);
        $indent = str_repeat('    ', $depth + 1);
        $written = false;

        foreach ($value as $key => $member) {
            $this->put($handle, ($written ? ",\n" : ($list ? '[' : '{') . "\n") . $indent . ($list ? '' : $this->encoded((string) $key, $depth + 1) . ': '));
            $this->writeValue($handle, $member, $depth + 1);
            $written = true;
        }

        // Empty, it is an empty list to the whole document's encoding.
        $this->put($handle, $written ? "\n" . str_repeat('    ', $depth) . ($list ? ']' : '}') : '[]');
    }

    /**
     * @param array<mixed> $value
     */
    private function holdsArrays(array $value): bool
    {
        foreach ($value as $member) {
            if (is_iterable($member)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The value as the whole document's encoding shows it at this depth.
     */
    private function encoded(mixed $value, int $depth): string
    {
        $json = json_encode($value, self::FLAGS);

        if ($json === false) {
            throw new RuntimeException('Failed to encode coverage data as JSON: ' . json_last_error_msg());
        }

        return str_replace("\n", "\n" . str_repeat('    ', $depth), $json);
    }

    /**
     * @param resource $handle
     */
    private function put($handle, string $text): void
    {
        if (fwrite($handle, $text) !== strlen($text)) {
            throw new RuntimeException('The coverage report could not be written whole.');
        }
    }
}
