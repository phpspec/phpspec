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

namespace PhpSpec\Ai\Agent;

use PhpSpec\Ai\PromptLibrary;

/**
 * @internal
 * The text of one exchange with the model, composed by CONVENTION in a fixed,
 * documented order (no template syntax): the command's own prose, then the
 * shared TDD cycle, then the current step's instruction file. Tool descriptions
 * ride the provider's tools payload, not the system prompt. `composedFrom`
 * names every prompt file used, so the capture log shows the exact assembly.
 */
final readonly class Request
{
    /**
     * @param string $system the composed system prompt
     * @param string $context the composed user message: grounding sections, instruction last
     * @param list<string> $composedFrom the prompt-library names composed into the system prompt
     */
    public function __construct(
        public string $system,
        public string $context,
        public array $composedFrom = [],
    ) {}

    /**
     * Composes the request for a command at a step, over a grounding.
     *
     * System order: `commands/<name>` body, `instructions/tdd-cycle`, then
     * `instructions/<phase>` prefixed with a "Current step" line carrying the
     * step's because. Context order: tree, named files, `# Instruction` last.
     */
    public static function compose(CommandProfile $profile, ?Step $step, Grounding $grounding, string $instruction, PromptLibrary $prompts): self
    {
        $composedFrom = ['commands/' . $profile->name];
        $sections = [$profile->body];

        $cycle = trim($prompts->read('instructions/tdd-cycle'));
        if ($cycle !== '') {
            $sections[] = $cycle;
            $composedFrom[] = 'instructions/tdd-cycle';
        }

        if ($step !== null) {
            $header = sprintf('Current step: %s (%s).', $step->phase->value, $step->because);
            $guide = trim($prompts->read('instructions/' . $step->phase->value));
            $sections[] = $guide !== '' ? $header . "\n\n" . $guide : $header;
            if ($guide !== '') {
                $composedFrom[] = 'instructions/' . $step->phase->value;
            }
        }

        return new self(implode("\n\n", $sections), self::context($grounding, $instruction), $composedFrom);
    }

    /**
     * The user message: grounding first (suite state, tree, then any named
     * files), the instruction last so the ask sits closest to the answer.
     */
    private static function context(Grounding $grounding, string $instruction): string
    {
        $sections = [];

        $suite = self::suiteText($grounding);
        if ($suite !== '') {
            $sections[] = $suite;
        }

        if (trim($grounding->tree) !== '') {
            $sections[] = "# Project files\n" . $grounding->tree;
        }

        foreach ($grounding->namedFiles as $path => $content) {
            $sections[] = "# $path\n$content";
        }

        $sections[] = "# Instruction\n$instruction";

        return implode("\n\n", $sections);
    }

    /**
     * The compact feature-state block for a grounding that carries a suite with
     * features: the counts, every non-green feature, and the last-touched
     * files. Empty for a spec-only (or unknown) suite, so spec-only projects
     * keep their leaner context.
     */
    public static function suiteText(Grounding $grounding): string
    {
        $suite = $grounding->suite;
        if ($suite === null || !$suite->hasFeatures()) {
            return '';
        }

        $counts = $suite->featureCounts();
        $lines = [sprintf(
            "# Suite state\nFEATURES: %d features, %d scenarios, %d steps (%d failing, %d undefined). Favour these: the outside drives the inside.",
            $counts['features'],
            $counts['scenarios'],
            $counts['steps'],
            $counts['stepFailures'],
            $counts['undefined'],
        )];

        foreach ($suite->features() as $feature) {
            if ($feature['status'] !== 'green') {
                $lines[] = sprintf('- %s: %s', $feature['status'], $feature['path']);
            }
        }

        if ($grounding->recentFeature !== null) {
            $lines[] = 'Last-touched feature: ' . $grounding->recentFeature;
        }

        if ($grounding->recentSource !== null) {
            $lines[] = 'Last-touched source: ' . $grounding->recentSource;
        }

        return implode("\n", $lines);
    }
}
