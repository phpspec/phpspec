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

/**
 * Renders an HTML code coverage report with an index page and per-file source views.
 * Lines are color-coded green (covered), red (uncovered), or grey (non-executable).
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
            $safeFile = htmlspecialchars($file);
            $linkFile = str_replace('/', '_', $file) . '.html';
            $barColor = $this->barColor($info['pct']);

            $rows .= <<<ROW
            <tr>
                <td><a href="$linkFile">$safeFile</a></td>
                <td>
                    <div class="bar"><div class="fill" style="width:{$info['pct']}%;background:$barColor"></div></div>
                </td>
                <td class="num">{$info['covered']}/{$info['executable']}</td>
                <td class="num">{$this->fmt($info['pct'])}</td>
            </tr>
            ROW;
        }

        $barColor = $this->barColor($totalPct);

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en"><head><meta charset="utf-8"><title>Code Coverage</title>
        <style>
        body { font-family: sans-serif; margin: 2em; }
        table { border-collapse: collapse; width: 100%; }
        th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #ddd; }
        .num { text-align: right; }
        .bar { background: #eee; height: 14px; border-radius: 3px; width: 200px; }
        .fill { height: 100%; border-radius: 3px; }
        .total { font-weight: bold; font-size: 1.1em; margin-top: 1em; }
        a { color: #0366d6; text-decoration: none; }
        a:hover { text-decoration: underline; }
        </style></head><body>
        <h1>Code Coverage Report</h1>
        <table>
        <tr><th>File</th><th>Coverage</th><th>Lines</th><th>%</th></tr>
        {$rows}
        </table>
        <p class="total">Total: $totalCovered/$totalExecutable ({$this->fmt($totalPct)})
        <div class="bar" style="width:300px"><div class="fill" style="width:$totalPct%;background:$barColor"></div></div>
        </p>
        </body></html>
        HTML;

        file_put_contents($dirPath . '/index.html', $html);
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
            $safeLine = htmlspecialchars($line);

            if (isset($lineData[$lineNo])) {
                $hit = $lineData[$lineNo];
                if ($hit === -2) {
                    $bg = '#f5f5f5';
                } elseif ($hit >= 1) {
                    $bg = '#dfd';
                } else {
                    $bg = '#fdd';
                }
            } else {
                $bg = '#f5f5f5';
            }

            $lines .= sprintf(
                '<tr style="background:%s"><td class="ln">%d</td><td class="code"><pre>%s</pre></td></tr>',
                $bg,
                $lineNo,
                rtrim($safeLine),
            );
        }

        $safeFile = htmlspecialchars($file);
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en"><head><meta charset="utf-8"><title>$safeFile - Coverage</title>
        <style>
        body { font-family: sans-serif; margin: 2em; }
        table { border-collapse: collapse; width: 100%; }
        td { padding: 0 8px; vertical-align: top; }
        .ln { text-align: right; color: #999; width: 50px; user-select: none; }
        .code pre { margin: 0; font-size: 13px; }
        a { color: #0366d6; }
        </style></head><body>
        <p><a href="index.html">Back to index</a></p>
        <h1>$safeFile</h1>
        <table>$lines</table>
        </body></html>
        HTML;

        $linkFile = str_replace('/', '_', $file) . '.html';
        file_put_contents($dirPath . '/' . $linkFile, $html);
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
     * Returns the CSS color for a coverage percentage bar.
     *
     * @param float $pct the coverage percentage
     * @return string the hex color code (green >= 80%, orange >= 50%, red otherwise)
     */
    private function barColor(float $pct): string
    {
        if ($pct >= 80) {
            return '#4caf50';
        }
        if ($pct >= 50) {
            return '#ff9800';
        }
        return '#f44336';
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
