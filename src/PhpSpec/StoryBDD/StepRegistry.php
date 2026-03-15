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

/**
 * Maps step patterns to closures for matching against Gherkin step text.
 * Patterns use {string}, {int}, {word}, and {*} placeholders that are converted to regex capture groups.
 */
final class StepRegistry
{
    /** @var array<int, array{pattern: string, regex: string, callback: \Closure}> registered step definitions */
    private array $steps = [];

    /**
     * Registers a step definition with a pattern and its implementing closure.
     *
     * @param string $pattern step pattern with optional {string}/{int}/{word}/{*} placeholders
     * @param \Closure $callback the closure to execute when the pattern matches
     * @return void
     */
    public function addStep(string $pattern, \Closure $callback): void
    {
        $this->steps[] = [
            'pattern' => $pattern,
            'regex' => $this->patternToRegex($pattern),
            'callback' => $callback,
        ];
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
