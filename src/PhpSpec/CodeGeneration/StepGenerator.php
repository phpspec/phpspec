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
     * The steps file a feature's definitions live in, in the standard layout
     * (`<feature dir>/steps/<name>.steps.php`). The single home of that
     * convention; {@see featurePathFor()} is its inverse.
     */
    public static function stepsPathFor(string $featurePath): string
    {
        return dirname($featurePath) . '/steps/' . basename($featurePath, '.feature') . '.steps.php';
    }

    /**
     * The feature a steps file belongs to, inverting {@see stepsPathFor()}.
     */
    public static function featurePathFor(string $stepsPath): string
    {
        return dirname($stepsPath, 2) . '/' . basename($stepsPath, '.steps.php') . '.feature';
    }

    /**
     * Generates step definition functions for the given undefined steps and writes them to a steps file.
     * Appends to an existing steps file if one already exists.
     *
     * @param string $featurePath absolute path to the .feature file
     * @param array<int, array{keyword: string, text: string}> $undefinedSteps list of undefined steps, each with 'keyword' and 'text' keys
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
     * appended for every step whose pattern is not already defined.
     *
     * @param array<int, array{keyword: string, text: string}> $steps steps with 'keyword' and 'text' keys
     * @param string $existing the current steps-file content, empty for a new file
     * @return string the complete new steps-file content
     */
    public function skeleton(array $steps, string $existing = ''): string
    {
        $content = $existing === '' ? "<?php\n" : rtrim($existing) . "\n";

        // "And"/"But" continue the primary keyword of the step they follow, so
        // an "And" under a When generates when(), under a Then generates then().
        $primary = 'given';

        foreach ($steps as $step) {
            $keyword = strtolower($step['keyword']);
            if (in_array($keyword, ['given', 'when', 'then'], true)) {
                $primary = $keyword;
            } else {
                $keyword = $primary;
            }
            $pattern = $this->extractPattern($step['text']);
            if ($existing !== '' && str_contains($existing, '"' . $pattern . '"')) {
                continue;
            }
            $params = $this->extractParams($pattern);

            $content .= "\n$keyword(\"$pattern\", function ($params) {\n";
            $content .= "    pending();\n";
            $content .= "});\n";
        }

        return $content;
    }

    /**
     * Extracts the Given/When/Then/And/But step lines from a feature's text, in
     * order, so a steps file can be drafted from the feature alone (no runner).
     *
     * @param string $featureText the raw contents of a .feature file
     * @return array<int, array{keyword: string, text: string}>
     */
    public static function parseSteps(string $featureText): array
    {
        $steps = [];

        foreach (preg_split('/\R/', $featureText) ?: [] as $line) {
            if (preg_match('~^\s*(Given|When|Then|And|But)\s+(.+?)\s*$~', $line, $matches) === 1) {
                $steps[] = ['keyword' => $matches[1], 'text' => $matches[2]];
            }
        }

        return $steps;
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
