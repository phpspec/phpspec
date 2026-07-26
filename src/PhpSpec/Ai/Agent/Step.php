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

use PhpSpec\Console\Command\Run\SuiteSummary;

/**
 * @internal
 * One deterministic answer to "what are we doing?": the current step of the
 * outside-in TDD cycle, resolved from the user's words first and the suite
 * state second. Carries its "because" so a wrong step is debugged by reading a
 * sentence. It names the phase and the subject only; resolving real file paths
 * through the configured layout belongs to the tools that act on the step.
 */
final readonly class Step
{
    private const STOP_WORDS = ['a', 'an', 'the', 'feature', 'scenario', 'story', 'describing', 'description', 'for', 'about', 'of', 'to', 'in', 'on', 'at', 'that', 'which', 'where'];

    /**
     * @param Phase $phase where we are in the cycle
     * @param string|null $path an explicit project-relative file path the user named, verbatim
     * @param string|null $subject the semantic subject: a class, a slug, or a feature path
     * @param string $because one sentence on why this step was chosen
     */
    public function __construct(
        public Phase $phase,
        public ?string $path = null,
        public ?string $subject = null,
        public string $because = '',
    ) {}

    /**
     * Resolves the current step: intent parsed from the instruction wins, the
     * suite state decides otherwise, and null means nothing determines the step
     * (the model may still act with the full cycle instructions).
     */
    public static function resolve(string $instruction, Grounding $grounding): ?self
    {
        return self::fromIntent($instruction, $grounding) ?? self::fromSuite($grounding->suite);
    }

    /**
     * The step named by the user's own words: an explicit path, a steps request,
     * or feature/spec/code wording with a subject. Null when the words commit to
     * nothing.
     */
    private static function fromIntent(string $instruction, Grounding $grounding): ?self
    {
        $path = preg_match('~[A-Za-z0-9_./-]+\.(?:feature|php)~', $instruction, $matches) ? $matches[0] : null;

        // A steps request: for a named feature, a named steps file, or (bare)
        // the last-touched feature. Checked before the extension mapping so
        // "the steps for x.feature" lands on write-steps, not write-feature.
        if (preg_match('~\bsteps?\b~i', $instruction) === 1) {
            if ($path !== null && str_ends_with($path, '.feature')) {
                return new self(Phase::WriteSteps, null, $path, sprintf('you asked for steps for "%s"', $path));
            }

            if ($path !== null && str_ends_with($path, '.steps.php')) {
                return new self(Phase::WriteSteps, $path, null, sprintf('you named "%s"', $path));
            }

            if ($path === null) {
                return $grounding->recentFeature !== null
                    ? new self(Phase::WriteSteps, null, $grounding->recentFeature, sprintf('steps for the last-touched feature "%s"', $grounding->recentFeature))
                    : new self(Phase::WriteSteps, null, null, 'you asked for steps, but no feature was found to attach them to');
            }
        }

        if ($path !== null) {
            $phase = match (true) {
                str_ends_with($path, '.feature') => Phase::WriteFeature,
                str_ends_with($path, '.spec.php') => Phase::WriteSpec,
                default => Phase::WriteCode,
            };

            return new self($phase, $path, null, sprintf('you named "%s"', $path));
        }

        if (preg_match('~\b(?:feature|scenario|story)\b~i', $instruction) === 1) {
            $slug = self::slug($instruction);
            if ($slug !== '') {
                return new self(Phase::WriteFeature, null, $slug, 'you asked for a feature');
            }
        }

        $class = self::classToken($instruction);
        if ($class !== null && preg_match('~\bspec\b~i', $instruction) === 1) {
            return new self(Phase::WriteSpec, null, $class, sprintf('you asked for a spec for "%s"', $class));
        }

        if ($class !== null && preg_match('~\b(?:implement|method|function)\b~i', $instruction) === 1) {
            return new self(Phase::WriteCode, null, $class, sprintf('you asked for code on "%s"', $class));
        }

        return null;
    }

    /**
     * The step the suite state points at, mirroring the narrator's order: with
     * features present, a failing example is fixed first, then a red feature is
     * descended into, then undefined steps are written, then green refactors.
     * Spec-only projects follow the classic describe/fill-the-gap/refactor loop.
     */
    private static function fromSuite(?SuiteSummary $suite): ?self
    {
        if ($suite === null) {
            return null;
        }

        $failure = $suite->failing()[0] ?? null;

        if ($suite->hasFeatures()) {
            if ($failure !== null) {
                return new self(Phase::WriteCode, null, $failure['subject'], sprintf('the suite is red: %s, %s', $failure['subject'], $failure['example']));
            }

            $red = $suite->redFeature();
            if ($red !== null) {
                return new self(Phase::WriteSpec, null, null, sprintf('a step is failing in "%s": descend and spec the behaviour it needs', $red['path']));
            }

            $todo = self::firstTodoFeature($suite);
            if ($todo !== null) {
                return new self(Phase::WriteSteps, null, $todo['path'], sprintf('%d undefined steps in "%s"', $todo['undefined'], $todo['path']));
            }

            return new self(Phase::Refactor, null, null, 'features are green: refactor, or grow the story');
        }

        if ($suite->isEmpty()) {
            return new self(Phase::WriteSpec, null, null, 'nothing here yet: describe the first class');
        }

        if ($suite->isRed()) {
            return new self(Phase::WriteCode, null, $failure['subject'] ?? null, sprintf('the suite is red: %s, %s', $failure['subject'] ?? '', $failure['example'] ?? ''));
        }

        $gap = $suite->nearestPendingGap();
        if ($gap !== null) {
            return new self(Phase::WriteSpec, null, $gap['subject'], sprintf('nearest pending gap: %s, %s', $gap['subject'], $gap['example']));
        }

        return new self(Phase::Refactor, null, null, 'green and clean: refactor, or pick the next behaviour');
    }

    /**
     * The first feature still carrying undefined steps, or null when none does.
     *
     * @return array{path: string, status: string, undefined: int}|null
     */
    private static function firstTodoFeature(SuiteSummary $suite): ?array
    {
        foreach ($suite->features() as $feature) {
            if ($feature['status'] === 'todo') {
                return $feature;
            }
        }

        return null;
    }

    /**
     * The first class-like token in the instruction, or null when none appears.
     */
    private static function classToken(string $instruction): ?string
    {
        return preg_match('~\b([A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)*)\b~', $instruction, $matches) ? $matches[1] : null;
    }

    /**
     * A snake_case subject slug from the instruction's meaningful words.
     */
    private static function slug(string $instruction): string
    {
        $words = preg_split('~[^a-z0-9]+~', strtolower($instruction), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, static fn(string $word): bool => !in_array($word, self::STOP_WORDS, true)));

        return implode('_', array_slice($words, 0, 6));
    }
}
