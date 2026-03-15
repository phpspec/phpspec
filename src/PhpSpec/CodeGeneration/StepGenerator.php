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
 * Generates step definition files for undefined Story BDD steps.
 * Creates a steps PHP file alongside the feature file with placeholder step implementations.
 */
class StepGenerator
{
    /**
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     */
    public function __construct(private ?Filesystem $filesystem = null)
    {
        $this->filesystem ??= new RealFilesystem();
    }

    /**
     * Generates step definition functions for the given undefined steps and writes them to a steps file.
     * Appends to an existing steps file if one already exists.
     *
     * @param string $featurePath absolute path to the .feature file
     * @param array $undefinedSteps list of undefined steps, each with 'keyword' and 'text' keys
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

        $content = "<?php\n";

        foreach ($undefinedSteps as $step) {
            $keyword = strtolower($step['keyword']);
            if ($keyword === 'and' || $keyword === 'but') {
                $keyword = 'given';
            }
            $pattern = $this->extractPattern($step['text']);
            $params = $this->extractParams($pattern);

            $content .= "\n$keyword(\"$pattern\", function ($params) {\n";
            $content .= "    pending();\n";
            $content .= "});\n";
        }

        if ($this->filesystem->exists($stepsFile)) {
            $existing = $this->filesystem->read($stepsFile);
            // Append new steps before the end
            $content = rtrim($existing) . "\n" . substr($content, 6); // skip <?php\n
        }

        $this->filesystem->write($stepsFile, $content);

        return $stepsFile;
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
        $pattern = preg_replace('/"[^"]*"/', '{string}', $text);
        // Convert standalone numbers to {int} placeholders
        return preg_replace('/\b(\d+)\b/', '{int}', $pattern);
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
