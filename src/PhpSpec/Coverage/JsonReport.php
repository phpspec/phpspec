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
     * The document is written a source file at a time, never held whole, to
     * a file beside the report that takes its place once complete.
     *
     * @param array<string, array{time: float, memory: int, spec_file: string, spec_checksum: string}> $tests per-example metadata keyed by test identifier
     * @param array<string, array{checksum: string, lines: array<int, array<int, string>>, executable?: list<int>, methods?: array<string, array{start: int, end: int}>}> $sources per-file checksums, line-to-test-identifier coverage and method spans, keyed by relative file path
     * @param string $filePath the absolute path to write the JSON file to
     * @throws RuntimeException when the document cannot be encoded as JSON, or written
     */
    public function render(array $tests, array $sources, string $filePath): void
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
     * @param array<string, mixed> $sources
     */
    private function write($handle, array $tests, array $sources): void
    {
        $this->put($handle, "{\n    \"version\": 1,\n    \"tests\": " . $this->encoded($tests, 1) . ",\n    \"sources\": ");

        if ($sources === []) {
            $this->put($handle, "[]\n}");

            return;
        }

        $separator = "{\n";
        foreach ($sources as $path => $source) {
            $this->put($handle, $separator . '        ' . $this->encoded((string) $path, 2) . ': ' . $this->encoded($source, 2));
            $separator = ",\n";
        }

        $this->put($handle, "\n    }\n}");
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
