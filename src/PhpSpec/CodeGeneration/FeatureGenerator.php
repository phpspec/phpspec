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

/**
 * @internal
 * Produces a valid Gherkin skeleton for a new feature — deterministically, so a
 * `.feature` never inherits the model's syntax. The generator owns the Gherkin;
 * the model only supplies meaning. Steps are undefined on purpose, so the
 * outside-in loop drives them next.
 */
final class FeatureGenerator
{
    /**
     * A one-scenario Gherkin skeleton with undefined Given/When/Then steps.
     */
    public function skeleton(string $title): string
    {
        return <<<GHERKIN
        Feature: {$title}

          Scenario: {$title}
            Given a starting context
            When something happens
            Then the outcome is checked

        GHERKIN;
    }

    /**
     * Derives a human title from a feature file path
     * (features/user_adds_tasks.feature → "User adds tasks").
     */
    public static function titleFromPath(string $path): string
    {
        $base = basename($path, '.feature');

        return ucfirst(trim(str_replace(['_', '-'], ' ', $base)));
    }
}
