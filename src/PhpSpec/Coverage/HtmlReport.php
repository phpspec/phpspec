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

use PhpSpec\Report\HtmlTheme;

/**
 * @internal
 * Renders an HTML code coverage report with an index page and per-file source
 * views, in the PhpSpec brand shared with the results formatter (HtmlTheme).
 * Lines are color-coded green (covered), red (uncovered), or plain
 * (non-executable).
 */
final class HtmlReport
{
    /**
     * Renders the coverage data as HTML files in the specified directory.
     * Creates an index.html overview and individual per-file HTML pages.
     *
     * @param array<string, array<int, int>> $data coverage data with relative file paths as keys
     * @param string $dirPath the directory path to write the HTML report to
     */
    public function render(array $data, string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0777, true);
        }

        $totalCovered = 0;
        $totalExecutable = 0;
        $files = [];

        foreach ($data as $file => $lines) {
            $counts = CoverageCollector::countLines($lines);
            $covered = $counts['covered'];
            $executable = $counts['executable'];

            $totalCovered += $covered;
            $totalExecutable += $executable;

            $pct = $executable > 0 ? ($covered / $executable) * 100 : 0;
            $files[$file] = [
                'lines' => $lines,
                'covered' => $covered,
                'executable' => $executable,
                'pct' => $pct,
            ];

            $this->renderFile($file, $lines, $dirPath);
        }

        $this->renderIndex($files, $totalCovered, $totalExecutable, $dirPath);
    }

    /**
     * Renders the index.html page with a summary table of all files and their coverage.
     *
     * @param array<string, array{lines: array<int, int>, covered: int, executable: int, pct: float}> $files per-file coverage info
     * @param int $totalCovered total number of covered lines across all files
     * @param int $totalExecutable total number of executable lines across all files
     * @param string $dirPath the directory to write index.html into
     */
    private function renderIndex(array $files, int $totalCovered, int $totalExecutable, string $dirPath): void
    {
        $totalPct = $totalExecutable > 0 ? ($totalCovered / $totalExecutable) * 100 : 0;

        $rows = '';
        foreach ($files as $file => $info) {
            $safeFile = htmlspecialchars($file, ENT_QUOTES);
            $linkFile = str_replace('/', '_', $file) . '.html';
            $grade = $this->barGrade($info['pct']);
            $width = sprintf('%.1f', $info['pct']);

            $rows .= <<<ROW
            <tr>
                <td><a href="$linkFile">$safeFile</a></td>
                <td><div class="bar"><span class="$grade" style="width:$width%"></span></div></td>
                <td class="num">{$info['covered']}/{$info['executable']}</td>
                <td class="num">{$this->fmt($info['pct'])}</td>
            </tr>
            ROW;
        }

        $meta = sprintf('%d/%d lines · %s', $totalCovered, $totalExecutable, $this->fmt($totalPct));

        $body = HtmlTheme::header('Coverage', $meta)
            . HtmlTheme::meter($totalPct)
            . "<main>\n"
            . "<table class=\"coverage\">\n"
            . "<tr><th>File</th><th>Coverage</th><th>Lines</th><th>%</th></tr>\n"
            . $rows
            . "\n</table>\n"
            . sprintf(
                "<footer class=\"summary\">\n<p>Total: %d/%d lines covered (%s)</p>\n</footer>\n",
                $totalCovered,
                $totalExecutable,
                $this->fmt($totalPct),
            )
            . "</main>\n";

        file_put_contents($dirPath . '/index.html', HtmlTheme::page('PhpSpec Coverage', $body));
    }

    /**
     * Renders an individual source file with color-coded coverage highlighting.
     *
     * @param string $file the relative source file path
     * @param array<int, int> $lineData line-level coverage data for this file
     * @param string $dirPath the directory to write the file HTML into
     */
    private function renderFile(string $file, array $lineData, string $dirPath): void
    {
        $srcPath = $this->findSourceFile($file);
        if ($srcPath === null) {
            return;
        }

        $source = file($srcPath);
        if ($source === false) {
            return;
        }
        $lines = '';

        foreach ($source as $i => $line) {
            $lineNo = $i + 1;
            $safeLine = htmlspecialchars($line, ENT_QUOTES);
            $hit = $lineData[$lineNo] ?? -2;

            $class = match (true) {
                $hit >= 1 => ' class="hit"',
                $hit === -2 => '',
                default => ' class="miss"',
            };

            $lines .= sprintf(
                '<tr%s><td class="ln">%d</td><td><pre>%s</pre></td></tr>',
                $class,
                $lineNo,
                rtrim($safeLine),
            );
        }

        $safeFile = htmlspecialchars($file, ENT_QUOTES);
        $counts = CoverageCollector::countLines($lineData);
        $pct = $counts['executable'] > 0 ? ($counts['covered'] / $counts['executable']) * 100 : 0;
        $meta = sprintf('%d/%d lines · %s', $counts['covered'], $counts['executable'], $this->fmt($pct));

        $body = HtmlTheme::header('Coverage', $meta)
            . HtmlTheme::meter($pct)
            . "<main>\n"
            . "<p><a class=\"back\" href=\"index.html\">‹ Back to index</a></p>\n"
            . "<h2>$safeFile</h2>\n"
            . "<table class=\"source\">$lines</table>\n"
            . "</main>\n";

        $linkFile = str_replace('/', '_', $file) . '.html';
        file_put_contents($dirPath . '/' . $linkFile, HtmlTheme::page("$safeFile — PhpSpec Coverage", $body));
    }

    /**
     * Attempts to locate the actual source file on disk from a relative path.
     *
     * @param string $relativePath the relative file path from coverage data
     * @return string|null the resolved path if found, or null
     */
    private function findSourceFile(string $relativePath): ?string
    {
        // Try common locations
        foreach (['src/', './src/', ''] as $prefix) {
            $path = $prefix . $relativePath;
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Returns the bar grade class for a coverage percentage.
     *
     * @param float $pct the coverage percentage
     * @return string "hi" (green, >= 80%), "mid" (amber, >= 50%) or "lo" (red)
     */
    private function barGrade(float $pct): string
    {
        if ($pct >= 80) {
            return 'hi';
        }
        if ($pct >= 50) {
            return 'mid';
        }
        return 'lo';
    }

    /**
     * Formats a percentage value for display.
     *
     * @param float $pct the percentage to format
     * @return string the formatted percentage string (e.g. "85.3%")
     */
    private function fmt(float $pct): string
    {
        return sprintf('%.1f%%', $pct);
    }
}
