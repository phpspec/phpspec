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
    /**
     * Renders the coverage data as a version 1 JSON document and writes it
     * to the specified file path. Creates parent directories if they do not exist.
     *
     * @param array<string, array{time: float, memory: int, spec_file: string, spec_checksum: string}> $tests per-example metadata keyed by test identifier
     * @param array<string, array{checksum: string, lines: array<int, array<int, string>>}> $sources per-file checksums and line-to-test-identifier coverage, keyed by relative file path
     * @param string $filePath the absolute path to write the JSON file to
     * @throws RuntimeException when the document cannot be encoded as JSON
     */
    public function render(array $tests, array $sources, string $filePath): void
    {
        $document = [
            'version' => 1,
            'tests' => $tests,
            'sources' => $sources,
        ];

        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode coverage data as JSON: ' . json_last_error_msg());
        }

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($filePath, $json);
    }
}
