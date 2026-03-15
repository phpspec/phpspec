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

use DOMDocument;
use DOMException;

/**
 * Renders a Clover XML code coverage report, compatible with CI tools like Jenkins and Codecov.
 * Produces a standard Clover coverage XML document with per-file line-level data and project metrics.
 */
final class CloverReport
{
    /**
     * Renders the coverage data as Clover XML and writes it to the specified file path.
     * Creates parent directories if they do not exist.
     *
     * @param array<string, array<int, int>> $data coverage data with relative file paths as keys
     * @param string $filePath the absolute path to write the Clover XML file to
     * @throws DOMException
     */
    public function render(array $data, string $filePath): void
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $coverage = $xml->createElement('coverage');
        $coverage->setAttribute('generated', (string) time());
        $xml->appendChild($coverage);

        $project = $xml->createElement('project');
        $project->setAttribute('timestamp', (string) time());
        $coverage->appendChild($project);

        $totalStatements = 0;
        $totalCoveredStatements = 0;

        foreach ($data as $file => $lines) {
            $fileEl = $xml->createElement('file');
            $fileEl->setAttribute('name', $file);

            foreach ($lines as $lineNo => $hit) {
                if ($hit === -2) {
                    continue;
                }

                $lineEl = $xml->createElement('line');
                $lineEl->setAttribute('num', (string) $lineNo);
                $lineEl->setAttribute('type', 'stmt');
                $lineEl->setAttribute('count', (string) max(0, $hit));
                $fileEl->appendChild($lineEl);
            }

            $counts = CoverageCollector::countLines($lines);
            $fileStatements = $counts['executable'];
            $fileCovered = $counts['covered'];

            if ($fileStatements === 0) {
                continue;
            }

            $metrics = $xml->createElement('metrics');
            $metrics->setAttribute('statements', (string) $fileStatements);
            $metrics->setAttribute('coveredstatements', (string) $fileCovered);
            $fileEl->appendChild($metrics);

            $project->appendChild($fileEl);

            $totalStatements += $fileStatements;
            $totalCoveredStatements += $fileCovered;
        }

        $projectMetrics = $xml->createElement('metrics');
        $projectMetrics->setAttribute('statements', (string) $totalStatements);
        $projectMetrics->setAttribute('coveredstatements', (string) $totalCoveredStatements);
        $project->appendChild($projectMetrics);

        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($filePath, $xml->saveXML());
    }
}
