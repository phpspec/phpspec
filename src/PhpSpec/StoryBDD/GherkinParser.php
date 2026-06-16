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
use Cucumber\Messages\Feature;
use Cucumber\Messages\GherkinDocument;
use Cucumber\Messages\Pickle;
use Cucumber\Messages\PickleStep;
use Cucumber\Messages\PickleStep\Type as PickleStepType;
use Cucumber\Messages\PickleTable;
use Cucumber\Messages\PickleTag;
use Cucumber\Messages\Scenario;
use Cucumber\Messages\Step;
use Cucumber\Messages\Tag;

/**
 * @internal
 * Parses .feature file content into a FeatureNode data structure.
 * Delegates to the official cucumber/gherkin parser and uses Cucumber pickles
 * for scenario expansion. Pickles automatically merge Background steps,
 * expand Scenario Outlines with Examples, and handle Rule children.
 */
final class GherkinParser
{
    /**
     * Parses raw Gherkin text into a FeatureNode containing scenarios and metadata.
     *
     * Uses Cucumber pickles for scenarios (which handle Background merging,
     * Scenario Outline expansion, and Rule children transparently) and the
     * GherkinDocument AST for feature-level metadata (name, description, tags).
     *
     * @param string $content the full text content of a .feature file
     * @param string $uri the file path used in error messages
     * @return FeatureNode the parsed feature structure
     */
    public function parse(string $content, string $uri = 'inline.feature'): FeatureNode
    {
        $parser = new CucumberParser(
            includeSource: false,
            includeGherkinDocument: true,
            includePickles: true,
        );

        $document = null;
        /** @var Pickle[] $pickles */
        $pickles = [];
        $errors = [];

        foreach ($parser->parseString($uri, $content) as $envelope) {
            if ($envelope->gherkinDocument !== null) {
                $document = $envelope->gherkinDocument;
            }
            if ($envelope->pickle !== null) {
                $pickles[] = $envelope->pickle;
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

        $stepIndex = $this->buildStepIndex($document);
        $featureTags = $this->mapTags($document->feature->tags);

        $scenarios = array_map(
            fn(Pickle $pickle) => $this->mapPickle($pickle, $stepIndex, $featureTags),
            $pickles,
        );

        return new FeatureNode(
            $document->feature->name,
            trim($document->feature->description),
            null,
            $scenarios,
            $featureTags,
        );
    }

    /**
     * Builds an index of AST step IDs to their keyword strings.
     * Walks the entire GherkinDocument AST including Feature children,
     * Rule children, backgrounds, and scenarios to capture all step keywords.
     *
     * @return array<string, string> map of step ID to trimmed keyword
     */
    private function buildStepIndex(GherkinDocument $document): array
    {
        $index = [];

        if ($document->feature === null) {
            return $index;
        }

        foreach ($document->feature->children as $child) {
            if ($child->background !== null) {
                $this->indexSteps($child->background->steps, $index);
            }
            if ($child->scenario !== null) {
                $this->indexSteps($child->scenario->steps, $index);
            }
            if ($child->rule !== null) {
                foreach ($child->rule->children as $ruleChild) {
                    if ($ruleChild->background !== null) {
                        $this->indexSteps($ruleChild->background->steps, $index);
                    }
                    if ($ruleChild->scenario !== null) {
                        $this->indexSteps($ruleChild->scenario->steps, $index);
                    }
                }
            }
        }

        return $index;
    }

    /**
     * Indexes an array of AST steps by their ID.
     *
     * @param Step[] $steps
     * @param array<string, string> $index
     */
    private function indexSteps(array $steps, array &$index): void
    {
        foreach ($steps as $step) {
            $index[$step->id] = trim($step->keyword);
        }
    }

    /**
     * Maps a Cucumber Pickle to a ScenarioNode.
     *
     * @param Pickle $pickle the pickle to map
     * @param array<string, string> $stepIndex AST step ID to keyword map
     * @param string[] $featureTags feature-level tags to exclude from scenario tags
     */
    private function mapPickle(Pickle $pickle, array $stepIndex, array $featureTags): ScenarioNode
    {
        $steps = array_map(
            fn(PickleStep $step) => $this->mapPickleStep($step, $stepIndex),
            $pickle->steps,
        );

        $pickleTags = array_map(
            fn(PickleTag $tag) => ltrim($tag->name, '@'),
            $pickle->tags,
        );

        // Pickles inherit feature tags; exclude them so scenario tags are scenario-only
        $scenarioTags = array_values(array_diff($pickleTags, $featureTags));

        return new ScenarioNode($pickle->name, $steps, $scenarioTags);
    }

    /**
     * Maps a PickleStep to a StepNode, resolving the keyword from the AST step index.
     *
     * @param PickleStep $step the pickle step to map
     * @param array<string, string> $stepIndex AST step ID to keyword map
     */
    private function mapPickleStep(PickleStep $step, array $stepIndex): StepNode
    {
        $keyword = $this->resolveKeyword($step, $stepIndex);

        $table = null;
        $docString = null;

        if ($step->argument !== null) {
            if ($step->argument->dataTable !== null) {
                $table = $this->mapPickleTable($step->argument->dataTable);
            }
            if ($step->argument->docString !== null) {
                $docString = $step->argument->docString->content;
            }
        }

        return new StepNode($keyword, $step->text, $table, $docString);
    }

    /**
     * Resolves the Gherkin keyword for a pickle step by looking up its AST node ID.
     * Falls back to mapping from PickleStep type if the AST node is not found.
     *
     * @param PickleStep $step the pickle step
     * @param array<string, string> $stepIndex AST step ID to keyword map
     */
    private function resolveKeyword(PickleStep $step, array $stepIndex): string
    {
        // The first astNodeId references the original AST Step
        if (!empty($step->astNodeIds)) {
            $astNodeId = $step->astNodeIds[0];
            if (isset($stepIndex[$astNodeId])) {
                return $stepIndex[$astNodeId];
            }
        }

        // Fallback: derive from PickleStep type
        return match ($step->type) {
            PickleStepType::CONTEXT => 'Given',
            PickleStepType::ACTION => 'When',
            PickleStepType::OUTCOME => 'Then',
            default => '',
        };
    }

    /**
     * Maps a PickleTable to a DataTable.
     */
    private function mapPickleTable(PickleTable $table): DataTable
    {
        return new DataTable(array_map(
            fn($row) => array_map(
                fn($cell) => $cell->value,
                $row->cells,
            ),
            $table->rows,
        ));
    }

    /**
     * @param Tag[] $tags
     * @return string[]
     */
    private function mapTags(array $tags): array
    {
        return array_map(fn(Tag $tag) => ltrim($tag->name, '@'), $tags);
    }
}
