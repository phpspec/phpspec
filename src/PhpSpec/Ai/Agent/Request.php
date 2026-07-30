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

use PhpSpec\Ai\Prompt;
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
        $composedFrom = [self::mark('commands/' . $profile->name, $profile->origin)];
        $sections = [$profile->body];

        $cycle = self::layer($prompts, 'instructions/tdd-cycle');
        if ($cycle !== null) {
            $sections[] = trim($cycle->text);
            $composedFrom[] = self::mark($cycle->name, $cycle->origin);
        }

        if ($step !== null) {
            $header = sprintf('Current step: %s (%s).', $step->phase->value, $step->because);
            $guide = self::layer($prompts, 'instructions/' . $step->phase->value);
            $sections[] = $guide !== null ? $header . "\n\n" . trim($guide->text) : $header;
            if ($guide !== null) {
                $composedFrom[] = self::mark($guide->name, $guide->origin);
            }
        }

        return new self(implode("\n\n", $sections), self::context($grounding, $instruction), $composedFrom);
    }

    /**
     * The winning layer for a name, or null when nothing non-empty resolves.
     */
    private static function layer(PromptLibrary $prompts, string $name): ?Prompt
    {
        $stack = $prompts->stack($name);
        if ($stack === [] || trim($stack[0]->text) === '') {
            return null;
        }

        return $stack[0];
    }

    /**
     * A composedFrom entry: the prompt name, marked when the project spoke, so
     * the capture log shows whose words the model heard.
     */
    private static function mark(string $name, string $origin): string
    {
        return $origin === Prompt::PROJECT ? $name . ' (project)' : $name;
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

        if ($grounding->polished !== []) {
            $sections[] = "# Refactoring journal\nAlready refactored and unchanged since: " . implode(', ', $grounding->polished) . '.';
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
