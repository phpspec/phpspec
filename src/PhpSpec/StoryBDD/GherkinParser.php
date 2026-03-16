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

use Cucumber\Gherkin\GherkinParser as CucumberParser;
use Cucumber\Messages\Background;
use Cucumber\Messages\DataTable as CucumberDataTable;
use Cucumber\Messages\Examples;
use Cucumber\Messages\Feature;
use Cucumber\Messages\FeatureChild;
use Cucumber\Messages\Scenario;
use Cucumber\Messages\Step;
use Cucumber\Messages\TableRow;
use Cucumber\Messages\Tag;

/**
 * Parses .feature file content into a FeatureNode data structure.
 * Delegates to the official cucumber/gherkin parser and maps the AST
 * to PhpSpec's node types.
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
        $parser = new CucumberParser(
            includeSource: false,
            includeGherkinDocument: true,
            includePickles: false,
        );

        $document = null;
        $errors = [];

        foreach ($parser->parseString('inline.feature', $content) as $envelope) {
            if ($envelope->gherkinDocument !== null) {
                $document = $envelope->gherkinDocument;
            }
            if ($envelope->parseError !== null) {
                $errors[] = $envelope->parseError->message;
            }
        }

        if (!empty($errors)) {
            throw new \RuntimeException('Gherkin parse error: ' . implode('; ', $errors));
        }

        if ($document === null || $document->feature === null) {
            return new FeatureNode('', '', null, []);
        }

        return $this->mapFeature($document->feature);
    }

    private function mapFeature(Feature $feature): FeatureNode
    {
        $background = null;
        $scenarios = [];

        foreach ($feature->children as $child) {
            if ($child->background !== null) {
                $background = $this->mapBackground($child->background);
            }
            if ($child->scenario !== null) {
                $scenarios[] = $this->mapScenario($child->scenario);
            }
        }

        return new FeatureNode(
            $feature->name,
            trim($feature->description),
            $background,
            $scenarios,
            $this->mapTags($feature->tags),
        );
    }

    private function mapBackground(Background $background): BackgroundNode
    {
        return new BackgroundNode(
            $this->mapSteps($background->steps),
        );
    }

    /**
     * @return ScenarioNode|ScenarioOutlineNode
     */
    private function mapScenario(Scenario $scenario): ScenarioNode
    {
        $steps = $this->mapSteps($scenario->steps);
        $tags = $this->mapTags($scenario->tags);

        if (!empty($scenario->examples)) {
            $examples = $this->mergeExamples($scenario->examples);
            return new ScenarioOutlineNode($scenario->name, $steps, $tags, $examples);
        }

        return new ScenarioNode($scenario->name, $steps, $tags);
    }

    /**
     * @param Step[] $steps
     * @return StepNode[]
     */
    private function mapSteps(array $steps): array
    {
        return array_map(fn (Step $step) => new StepNode(
            trim($step->keyword),
            $step->text,
            $step->dataTable !== null ? $this->mapDataTable($step->dataTable) : null,
            $step->docString?->content,
        ), $steps);
    }

    private function mapDataTable(CucumberDataTable $table): DataTable
    {
        return new DataTable(array_map(
            fn (TableRow $row) => array_map(
                fn ($cell) => $cell->value,
                $row->cells,
            ),
            $table->rows,
        ));
    }

    /**
     * Merges all Examples blocks into a single DataTable.
     *
     * @param Examples[] $examplesList
     */
    private function mergeExamples(array $examplesList): ?DataTable
    {
        $rawRows = [];
        $header = null;

        foreach ($examplesList as $examples) {
            if ($examples->tableHeader !== null && $header === null) {
                $header = array_map(fn ($cell) => $cell->value, $examples->tableHeader->cells);
                $rawRows[] = $header;
            }
            foreach ($examples->tableBody as $row) {
                $rawRows[] = array_map(fn ($cell) => $cell->value, $row->cells);
            }
        }

        return !empty($rawRows) ? new DataTable($rawRows) : null;
    }

    /**
     * @param Tag[] $tags
     * @return string[]
     */
    private function mapTags(array $tags): array
    {
        return array_map(fn (Tag $tag) => ltrim($tag->name, '@'), $tags);
    }
}
