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
 * Extends ScenarioNode for Scenario Outline with an Examples table.
 * Expands placeholder variables into concrete ScenarioNode instances.
 */
readonly class ScenarioOutlineNode extends ScenarioNode
{
    /**
     * Creates a ScenarioOutlineNode with template steps and an optional Examples table.
     *
     * @param string $title the outline title
     * @param StepNode[] $steps template steps with <placeholder> variables
     * @param string[] $tags tags applied to the outline
     * @param ?DataTable $examples the Examples table providing values for placeholders
     */
    public function __construct(
        string $title,
        array $steps,
        array $tags = [],
        public ?DataTable $examples = null,
    ) {
        parent::__construct($title, $steps, $tags);
    }

    /**
     * Expands the outline into concrete ScenarioNode instances by substituting
     * <placeholder> variables with values from each row of the Examples table.
     *
     * @return ScenarioNode[] one scenario per Examples row, or a single scenario if no examples
     */
    public function expand(): array
    {
        if ($this->examples === null || count($this->examples) === 0) {
            return [new ScenarioNode($this->title, $this->steps, $this->tags)];
        }

        $scenarios = [];
        foreach ($this->examples as $row) {
            $expandedSteps = [];
            foreach ($this->steps as $step) {
                $text = $step->text;
                foreach ($row as $key => $value) {
                    $text = str_replace('<' . $key . '>', $value, $text);
                }
                $expandedSteps[] = new StepNode($step->keyword, $text, $step->table);
            }

            $titleSuffix = implode(', ', array_values($row));
            $scenarios[] = new ScenarioNode(
                $this->title . ' (' . $titleSuffix . ')',
                $expandedSteps,
                $this->tags,
            );
        }

        return $scenarios;
    }
}
