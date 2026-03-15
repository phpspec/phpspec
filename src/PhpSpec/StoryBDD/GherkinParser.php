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
 * Parses .feature file content into a FeatureNode data structure.
 * Supports Background, Scenario, Scenario Outline with Examples tables, DataTables,
 * Doc strings, tags, and And/But step keywords.
 */
final class GherkinParser
{
    /**
     * Parses raw Gherkin text into a FeatureNode containing scenarios, background, and metadata.
     *
     * @param string $content the full text content of a .feature file
     * @return FeatureNode the parsed feature structure
     */
    public function parse(string $content): FeatureNode
    {
        $lines = explode("\n", $content);
        $title = '';
        $description = '';
        $background = null;
        $scenarios = [];
        $currentSection = null; // 'description', 'background', 'scenario', 'outline', 'examples'
        $currentSteps = [];
        $currentScenarioTitle = '';
        $descriptionLines = [];
        $pendingTags = [];
        $featureTags = [];
        $currentScenarioTags = [];
        $isOutline = false;
        $examplesRows = [];
        $tableRows = [];
        $lastStep = null;
        $inDocString = false;
        $docStringLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Doc string handling
            if ($trimmed === '"""') {
                if ($inDocString) {
                    // Close doc string — attach to last step
                    $this->attachDocString($lastStep, $docStringLines, $currentSteps);
                    $docStringLines = [];
                    $inDocString = false;
                } else {
                    $inDocString = true;
                    $docStringLines = [];
                }
                continue;
            }

            if ($inDocString) {
                $docStringLines[] = $line;
                continue;
            }

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                if ($currentSection === 'description') {
                    $descriptionLines[] = '';
                }
                continue;
            }

            // Tags line: @tag1 @tag2
            if (str_starts_with($trimmed, '@')) {
                $pendingTags = array_merge($pendingTags, $this->parseTags($trimmed));
                continue;
            }

            if (str_starts_with($trimmed, 'Feature:')) {
                $title = trim(substr($trimmed, 8));
                $featureTags = $pendingTags;
                $pendingTags = [];
                $currentSection = 'description';
                continue;
            }

            if (str_starts_with($trimmed, 'Background:')) {
                $this->attachTable($lastStep, $tableRows, $currentSteps);
                $tableRows = [];
                $lastStep = null;
                $this->flushScenario($scenarios, $currentSection, $currentScenarioTitle, $currentSteps, $currentScenarioTags, $isOutline, $examplesRows);
                $currentSection = 'background';
                $currentSteps = [];
                $isOutline = false;
                $examplesRows = [];
                $pendingTags = [];
                continue;
            }

            if (str_starts_with($trimmed, 'Scenario Outline:') || str_starts_with($trimmed, 'Scenario Template:')) {
                $this->attachTable($lastStep, $tableRows, $currentSteps);
                $tableRows = [];
                $lastStep = null;
                if ($currentSection === 'background') {
                    $background = new BackgroundNode($currentSteps);
                    $currentSteps = [];
                }
                $this->flushScenario($scenarios, $currentSection, $currentScenarioTitle, $currentSteps, $currentScenarioTags, $isOutline, $examplesRows);
                $currentSection = 'outline';
                $prefix = str_starts_with($trimmed, 'Scenario Outline:') ? 'Scenario Outline:' : 'Scenario Template:';
                $currentScenarioTitle = trim(substr($trimmed, strlen($prefix)));
                $currentSteps = [];
                $currentScenarioTags = $pendingTags;
                $pendingTags = [];
                $isOutline = true;
                $examplesRows = [];
                continue;
            }

            if (str_starts_with($trimmed, 'Scenario:')) {
                $this->attachTable($lastStep, $tableRows, $currentSteps);
                $tableRows = [];
                $lastStep = null;
                if ($currentSection === 'background') {
                    $background = new BackgroundNode($currentSteps);
                    $currentSteps = [];
                }
                $this->flushScenario($scenarios, $currentSection, $currentScenarioTitle, $currentSteps, $currentScenarioTags, $isOutline, $examplesRows);
                $currentSection = 'scenario';
                $currentScenarioTitle = trim(substr($trimmed, 9));
                $currentSteps = [];
                $currentScenarioTags = $pendingTags;
                $pendingTags = [];
                $isOutline = false;
                $examplesRows = [];
                continue;
            }

            if (str_starts_with($trimmed, 'Examples:') || str_starts_with($trimmed, 'Scenarios:')) {
                $this->attachTable($lastStep, $tableRows, $currentSteps);
                $tableRows = [];
                $lastStep = null;
                $currentSection = 'examples';
                $examplesRows = [];
                continue;
            }

            // Table row: | col1 | col2 |
            if (str_starts_with($trimmed, '|')) {
                $cells = $this->parseTableRow($trimmed);
                if ($currentSection === 'examples') {
                    $examplesRows[] = $cells;
                } else {
                    $tableRows[] = $cells;
                }
                continue;
            }

            if ($currentSection === 'description') {
                $descriptionLines[] = $trimmed;
                continue;
            }

            if ($currentSection === 'background' || $currentSection === 'scenario' || $currentSection === 'outline') {
                // Flush any pending table rows to the last step
                $this->attachTable($lastStep, $tableRows, $currentSteps);
                $tableRows = [];

                $step = $this->parseStep($trimmed);
                if ($step !== null) {
                    $currentSteps[] = $step;
                    $lastStep = $step;
                }
            }
        }

        // Flush any trailing table rows
        $this->attachTable($lastStep, $tableRows, $currentSteps);

        if ($currentSection === 'background') {
            $background = new BackgroundNode($currentSteps);
        } else {
            $this->flushScenario($scenarios, $currentSection, $currentScenarioTitle, $currentSteps, $currentScenarioTags, $isOutline, $examplesRows);
        }

        $description = trim(implode("\n", $descriptionLines));

        return new FeatureNode($title, $description, $background, $scenarios, $featureTags);
    }

    /**
     * Extracts tag names from a line like "@tag1 @tag2".
     *
     * @param string $line the raw line containing @-prefixed tags
     * @return string[] tag names without the @ prefix
     */
    private function parseTags(string $line): array
    {
        preg_match_all('/@(\S+)/', $line, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Splits a pipe-delimited table row into an array of trimmed cell values.
     *
     * @param string $line a row like "| col1 | col2 |"
     * @return string[] the cell values
     */
    private function parseTableRow(string $line): array
    {
        $line = trim($line, '|');
        return array_map('trim', explode('|', $line));
    }

    /**
     * Parses a line into a StepNode if it begins with a recognized keyword (Given/When/Then/And/But).
     *
     * @param string $line the trimmed line to parse
     * @return StepNode|null null if the line does not match any step keyword
     */
    private function parseStep(string $line): ?StepNode
    {
        $keywords = ['Given', 'When', 'Then', 'And', 'But'];

        foreach ($keywords as $keyword) {
            if (str_starts_with($line, $keyword . ' ')) {
                $text = trim(substr($line, strlen($keyword) + 1));
                return new StepNode($keyword, $text);
            }
        }

        return null;
    }

    /**
     * Attaches accumulated table rows as a DataTable to the most recent step.
     * Replaces the last step in the steps array with a new StepNode that includes the table.
     *
     * @param ?StepNode $lastStep the step to attach the table to
     * @param mixed $tableRows accumulated raw table rows; cleared after attachment
     * @param mixed $currentSteps the current steps array, modified in place
     */
    private function attachTable(?StepNode $lastStep, array &$tableRows, array &$currentSteps): void
    {
        if (empty($tableRows) || $lastStep === null) {
            $tableRows = [];
            return;
        }

        $table = new DataTable($tableRows);
        $index = array_key_last($currentSteps);
        if ($index !== null) {
            $currentSteps[$index] = new StepNode($lastStep->keyword, $lastStep->text, $table, $lastStep->docString);
        }
        $tableRows = [];
    }

    /**
     * Attaches a doc string (triple-quoted block) to the most recent step.
     * Dedents the content by the minimum indentation of non-empty lines.
     *
     * @param ?StepNode $lastStep the step to attach the doc string to
     * @param array $docStringLines raw lines between the triple-quote delimiters
     * @param mixed $currentSteps the current steps array, modified in place
     */
    private function attachDocString(?StepNode $lastStep, array $docStringLines, array &$currentSteps): void
    {
        if ($lastStep === null) {
            return;
        }

        // Dedent: find minimum indentation of non-empty lines
        $minIndent = PHP_INT_MAX;
        foreach ($docStringLines as $line) {
            if (trim($line) !== '') {
                $indent = strlen($line) - strlen(ltrim($line));
                $minIndent = min($minIndent, $indent);
            }
        }
        if ($minIndent === PHP_INT_MAX) {
            $minIndent = 0;
        }

        $dedented = array_map(function (string $line) use ($minIndent) {
            return strlen($line) >= $minIndent ? substr($line, $minIndent) : $line;
        }, $docStringLines);

        $docString = implode("\n", $dedented);

        $index = array_key_last($currentSteps);
        if ($index !== null) {
            $currentSteps[$index] = new StepNode($lastStep->keyword, $lastStep->text, $lastStep->table, $docString);
        }
    }

    /**
     * Finalizes the current scenario (or outline) and appends it to the scenarios list.
     * Creates a ScenarioOutlineNode for outlines with Examples, or a plain ScenarioNode otherwise.
     *
     * @param mixed $scenarios accumulator for completed scenario nodes; modified in place
     * @param ?string $section the current parser section (scenario/outline/examples)
     * @param string $title the scenario title
     * @param array $steps the steps collected for this scenario
     * @param array $tags tags applied to the scenario
     * @param bool $isOutline whether this is a Scenario Outline
     * @param array $examplesRows raw rows from the Examples table
     */
    private function flushScenario(
        array &$scenarios,
        ?string $section,
        string $title,
        array $steps,
        array $tags,
        bool $isOutline,
        array $examplesRows,
    ): void {
        if (($section === 'scenario' || $section === 'outline' || $section === 'examples') && !empty($steps)) {
            if ($isOutline) {
                $examples = !empty($examplesRows) ? new DataTable($examplesRows) : null;
                $scenarios[] = new ScenarioOutlineNode($title, $steps, $tags, $examples);
            } else {
                $scenarios[] = new ScenarioNode($title, $steps, $tags);
            }
        }
    }
}
