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

use PhpSpec\Filesystem;

/**
 * @internal
 * Assembles the data for the JSON coverage report from a per-example
 * collection: filters covered files down to the source path, relativizes
 * paths against the project root, and adds source/spec file checksums so
 * consumers can detect stale coverage data.
 */
final class JsonReportBuilder
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * Builds the tests and sources sections of the JSON coverage report.
     *
     * @param PerExampleCollector $collector the collector holding per-example coverage
     * @param string $srcPath absolute path to the source directory to include
     * @param string $projectRoot absolute path to the project root used to relativize paths
     * @return array{tests: array<string, array{time: float, memory: int, spec_file: string, spec_checksum: string}>, sources: array<string, array{checksum: string, lines: array<int, array<int, string>>}>}
     */
    public function build(PerExampleCollector $collector, string $srcPath, string $projectRoot): array
    {
        return [
            'tests' => $this->buildTests($collector->getTests()),
            'sources' => $this->buildSources($collector->getLines(), $srcPath, $projectRoot, $collector->getAggregate()),
        ];
    }

    /**
     * Adds a content checksum of each spec file to the per-test metadata.
     *
     * @param array<string, array{time: float, memory: int, spec_file: string}> $tests per-test metadata keyed by test identifier
     * @return array<string, array{time: float, memory: int, spec_file: string, spec_checksum: string}>
     */
    private function buildTests(array $tests): array
    {
        $checksums = [];
        $result = [];

        foreach ($tests as $testId => $test) {
            $specFile = $test['spec_file'];
            $checksums[$specFile] ??= md5($this->filesystem->read($specFile));
            $result[$testId] = $test + ['spec_checksum' => $checksums[$specFile]];
        }

        return $result;
    }

    /**
     * The lines of one file that were executable, whether or not anything
     * reached them: Xdebug marks the rest -2.
     *
     * @param array<int, int> $hits line => hit value
     * @return list<int>
     */
    private static function executable(array $hits): array
    {
        $lines = [];
        foreach ($hits as $line => $hit) {
            if ($hit !== -2) {
                $lines[] = $line;
            }
        }
        sort($lines);

        return $lines;
    }

    /**
     * Filters covered files down to the source path, relativizes them against
     * the project root and pairs each with a content checksum. Paths are
     * normalised to forward slashes on every platform (Xdebug and realpath()
     * report backslash paths on Windows).
     *
     * @param array<string, array<int, array<int, string>>> $lines file path => line number => test identifiers
     * @param string $srcPath absolute path to the source directory to include
     * @param string $projectRoot absolute path to the project root used to relativize paths
     * @param array<string, array<int, int>> $aggregate line hit values, for which lines were executable at all
     * @return array<string, array{checksum: string, lines: array<int, array<int, string>>, executable: list<int>}>
     */
    private function buildSources(array $lines, string $srcPath, string $projectRoot, array $aggregate = []): array
    {
        $srcPrefix = rtrim(str_replace('\\', '/', $srcPath), '/') . '/';
        $rootPrefix = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/';
        $sources = [];

        foreach ($lines as $file => $fileLines) {
            $normalized = str_replace('\\', '/', $file);

            if (!str_starts_with($normalized, $srcPrefix) || str_contains($normalized, "eval()'d code")) {
                continue;
            }

            $relative = str_starts_with($normalized, $rootPrefix) ? substr($normalized, strlen($rootPrefix)) : $normalized;
            $sources[$relative] = [
                'checksum' => md5($this->filesystem->read($file)),
                'lines' => $fileLines,
                // Which lines there were to reach at all, so a reader of the
                // artifact can tell a line nothing reached from a line that
                // was never code: "lines" holds only what ran.
                'executable' => self::executable($aggregate[$file] ?? []),
            ];
        }

        ksort($sources);

        return $sources;
    }
}
