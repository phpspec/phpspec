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

namespace PhpSpec\CodeGeneration;

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * Generates step definition files for undefined Story BDD steps.
 * Creates a steps PHP file alongside the feature file with placeholder step implementations.
 */
class StepGenerator
{
    private readonly Filesystem $filesystem;

    /**
     * @param Filesystem $filesystem filesystem abstraction for testability
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Generates step definition functions for the given undefined steps and writes them to a steps file.
     * Appends to an existing steps file if one already exists.
     *
     * @param string $featurePath absolute path to the .feature file
     * @param array<int, array{keyword: string, text: string, table?: bool, docString?: bool}> $undefinedSteps list of undefined steps, each with 'keyword' and 'text' keys, and whether a table or a doc string follows it
     * @return string the path to the generated/updated steps file
     */
    public function generate(string $featurePath, array $undefinedSteps): string
    {
        $featureDir = dirname($featurePath);
        $stepsDir = $featureDir . '/steps';
        $featureName = pathinfo($featurePath, PATHINFO_FILENAME);
        $stepsFile = $stepsDir . '/' . $featureName . '.steps.php';

        if (!$this->filesystem->exists($stepsDir)) {
            $this->filesystem->mkdir($stepsDir);
        }

        $existing = $this->filesystem->exists($stepsFile) ? $this->filesystem->read($stepsFile) : '';

        $this->filesystem->write($stepsFile, $this->skeleton($undefinedSteps, $existing));

        return $stepsFile;
    }

    /**
     * Drafts the complete content of a steps file for the given steps without
     * touching disk: existing content (when any) with a placeholder function
     * appended for every step whose pattern is not already defined. A step
     * that carries a table takes it as a DataTable, one that carries a doc
     * string takes it as a string, after the parameters of its pattern.
     *
     * @param array<int, array{keyword: string, text: string, table?: bool, docString?: bool}> $steps steps with 'keyword' and 'text' keys, and whether a table or a doc string follows
     * @param string $existing the current steps-file content, empty for a new file
     * @param list<string> $definedElsewhere titles other steps files already define, never re-scaffolded
     * @return string the complete new steps-file content
     */
    public function skeleton(array $steps, string $existing = '', array $definedElsewhere = []): string
    {
        $content = $existing === '' ? "<?php\n" : rtrim($existing) . "\n";

        // "And"/"But" continue the primary keyword of the step they follow, so
        // an "And" under a When generates when(), under a Then generates then().
        $primary = 'given';

        // One definition per title: a step used twice in a scenario (or an
        // already-defined one, whichever quotes it uses) registers once, and a
        // second definition would now be rejected at load.
        $emitted = [];

        foreach ($steps as $step) {
            $keyword = strtolower($step['keyword']);
            if (in_array($keyword, ['given', 'when', 'then'], true)) {
                $primary = $keyword;
            } else {
                $keyword = $primary;
            }
            $pattern = $this->extractPattern($step['text']);
            if (isset($emitted[$pattern]) || in_array($pattern, $definedElsewhere, true) || ($existing !== '' && $this->defines($existing, $pattern))) {
                continue;
            }
            $emitted[$pattern] = true;
            $params = $this->extractParams($pattern);

            if ($step['table'] ?? false) {
                $params .= ($params === '' ? '' : ', ') . 'DataTable $table';
                $needsDataTable = true;
            } elseif ($step['docString'] ?? false) {
                $params .= ($params === '' ? '' : ', ') . 'string $docString';
            }

            $content .= "\n$keyword(\"$pattern\", function ($params) {\n";
            $content .= "    pending();\n";
            $content .= "});\n";
        }

        if (($needsDataTable ?? false) && !str_contains($content, 'use PhpSpec\\StoryBDD\\DataTable;')) {
            $content = (string) preg_replace('/^<\?php\n/', "<?php\n\nuse PhpSpec\\StoryBDD\\DataTable;\n", $content, 1);
        }

        return $content;
    }

    /**
     * Whether a steps file's content already defines the given pattern, in
     * either quoting style.
     */
    private function defines(string $content, string $pattern): bool
    {
        return str_contains($content, '"' . $pattern . '"') || str_contains($content, "'" . $pattern . "'");
    }

    /**
     * Extracts the Given/When/Then/And/But step lines from a feature's text, in
     * order, so a steps file can be drafted from the feature alone (no runner),
     * each noting the table or doc string that follows it. An Examples table
     * belongs to its outline, not to the step above it.
     *
     * @param string $featureText the raw contents of a .feature file
     * @return array<int, array{keyword: string, text: string, table?: bool, docString?: bool}>
     */
    public static function parseSteps(string $featureText): array
    {
        $steps = [];
        $keyword = null;
        $text = '';
        $table = false;
        $docString = false;
        $inDocString = false;

        foreach (preg_split('/\R/', $featureText) ?: [] as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '"""')) {
                $inDocString = !$inDocString;
                $docString = $docString || $inDocString;

                continue;
            }

            if ($inDocString) {
                continue;
            }

            if (preg_match('~^\s*(Given|When|Then|And|But)\s+(.+?)\s*$~', $line, $matches) === 1) {
                if ($keyword !== null) {
                    $steps[] = self::step($keyword, $text, $table, $docString);
                }
                [$keyword, $text, $table, $docString] = [$matches[1], $matches[2], false, false];
            } elseif (str_starts_with($trimmed, '|')) {
                $table = true;
            } elseif ($trimmed !== '' && !str_starts_with($trimmed, '#') && $keyword !== null) {
                $steps[] = self::step($keyword, $text, $table, $docString);
                $keyword = null;
            }
        }

        if ($keyword !== null) {
            $steps[] = self::step($keyword, $text, $table, $docString);
        }

        return $steps;
    }

    /**
     * @return array{keyword: string, text: string, table?: bool, docString?: bool}
     */
    private static function step(string $keyword, string $text, bool $table, bool $docString): array
    {
        return ['keyword' => $keyword, 'text' => $text]
            + ($table ? ['table' => true] : [])
            + ($docString ? ['docString' => true] : []);
    }

    /**
     * Converts step text into a pattern with typed placeholders for quoted strings and numbers.
     *
     * @param string $text the raw step text
     * @return string the pattern with {string} and {int} placeholders
     */
    private function extractPattern(string $text): string
    {
        // Convert quoted strings to {string} placeholders
        $pattern = preg_replace('/"[^"]*"/', '{string}', $text) ?? $text;
        // Convert standalone numbers to {int} placeholders
        return preg_replace('/\b(\d+)\b/', '{int}', $pattern) ?? $pattern;
    }

    /**
     * Builds a typed parameter list string from the placeholders found in a step pattern.
     *
     * @param string $pattern the step pattern containing typed placeholders
     * @return string comma-separated typed parameter declarations (e.g. "string $arg1, int $arg2")
     */
    private function extractParams(string $pattern): string
    {
        $params = [];
        $index = 0;

        preg_match_all('/{(string|int|word|\*)}/', $pattern, $matches);

        foreach ($matches[1] as $type) {
            $index++;
            $paramType = match ($type) {
                'int' => 'int',
                default => 'string',
            };
            $params[] = "$paramType \$arg$index";
        }

        return implode(', ', $params);
    }
}
