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
 * @internal
 * Selects the scenarios addressed by a "file.feature:LINE" run target.
 * A line matching a scenario keyword selects that scenario (every expansion,
 * for outlines); a line matching an examples table row selects that single
 * expansion; any other line selects the nearest scenario declared above it.
 */
final class ScenarioLineSelector
{
    /**
     * Selects the scenarios matching the given line number.
     *
     * @param ScenarioNode[] $scenarios all scenarios of the feature, in declaration order
     * @param int $line the targeted line number
     * @return ScenarioNode[] the selected scenarios, empty when the line precedes every scenario
     */
    public static function select(array $scenarios, int $line): array
    {
        $exact = array_values(array_filter(
            $scenarios,
            fn(ScenarioNode $scenario) => $scenario->line === $line || $scenario->exampleLine === $line,
        ));

        if ($exact !== []) {
            return $exact;
        }

        $nearest = 0;

        foreach ($scenarios as $scenario) {
            if ($scenario->line <= $line) {
                $nearest = max($nearest, $scenario->line);
            }
        }

        if ($nearest === 0) {
            return [];
        }

        return array_values(array_filter(
            $scenarios,
            fn(ScenarioNode $scenario) => $scenario->line === $nearest,
        ));
    }
}
