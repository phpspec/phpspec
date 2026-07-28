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

namespace PhpSpec\StoryBDD;

use RuntimeException;

/**
 * @internal
 * Maps step patterns to closures for matching against Gherkin step text.
 * Patterns use {string}, {int}, {word}, and {*} placeholders that are converted to regex capture groups.
 * A title registers once: matching is global and keyword-blind, so a second
 * definition of the same title (even under another keyword) could only shadow
 * or be shadowed silently, and is rejected instead.
 */
final class StepRegistry
{
    /** @var array<int, array{pattern: string, regex: string, callback: \Closure}> registered step definitions */
    private array $steps = [];

    /** @var array<string, string> pattern => source location of its definition */
    private array $definedAt = [];

    /**
     * Registers a step definition with a pattern and its implementing closure.
     *
     * @param string $pattern step pattern with optional {string}/{int}/{word}/{*} placeholders
     * @param \Closure $callback the closure to execute when the pattern matches
     * @return void
     *
     * @throws RuntimeException when the pattern is already registered
     */
    public function addStep(string $pattern, \Closure $callback): void
    {
        if (isset($this->definedAt[$pattern])) {
            throw new RuntimeException(sprintf(
                'Step "%s" is already defined at %s; remove the duplicate at %s. Step titles must be unique across all steps files, whatever their keyword.',
                $pattern,
                $this->definedAt[$pattern],
                self::locationOf($callback),
            ));
        }

        $this->definedAt[$pattern] = self::locationOf($callback);
        $this->steps[] = [
            'pattern' => $pattern,
            'regex' => $this->patternToRegex($pattern),
            'callback' => $callback,
        ];
    }

    /**
     * The file:line a step closure was written at, relative to the project when
     * possible, so a duplicate error points straight at both definitions.
     */
    private static function locationOf(\Closure $callback): string
    {
        $reflection = new \ReflectionFunction($callback);
        $file = $reflection->getFileName() ?: 'unknown';
        $cwd = getcwd();
        if (is_string($cwd) && str_starts_with($file, $cwd . DIRECTORY_SEPARATOR)) {
            $file = substr($file, strlen($cwd) + 1);
        }

        return $file . ':' . ($reflection->getStartLine() ?: 0);
    }

    /**
     * Finds the first registered step pattern matching the given text.
     *
     * @param string $text the step text to match against registered patterns
     * @return StepMatch|null the match with callback and captured args, or null if no match
     */
    public function match(string $text): ?StepMatch
    {
        foreach ($this->steps as $step) {
            if (preg_match($step['regex'], $text, $matches)) {
                array_shift($matches);
                return new StepMatch($step['callback'], $matches, $step['pattern']);
            }
        }

        return null;
    }

    /**
     * Removes all registered step definitions.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->steps = [];
        $this->definedAt = [];
    }

    /**
     * Returns the number of registered step definitions.
     *
     * @return int the count of registered steps
     */
    public function count(): int
    {
        return count($this->steps);
    }

    /**
     * Converts a step pattern with placeholders into a full-match regex.
     * Replaces {string} with quoted capture, {int} with digit capture,
     * {word} with word capture, and {*} with greedy capture.
     *
     * @param string $pattern the step pattern with placeholders
     * @return string the compiled regex with anchors
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '/');

        // {string} → "([^"]*)"
        $regex = str_replace('\\{string\\}', '"([^"]*)"', $regex);
        // {int} → (\d+)
        $regex = str_replace('\\{int\\}', '(\d+)', $regex);
        // {word} → (\w+)
        $regex = str_replace('\\{word\\}', '(\w+)', $regex);
        // {*} → (.+)
        $regex = str_replace('\\{\\*\\}', '(.+)', $regex);

        return '/^' . $regex . '$/';
    }
}
