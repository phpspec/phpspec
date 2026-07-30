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

namespace PhpSpec\Ai;

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;
use RuntimeException;

/**
 * @internal
 * Resolves a named prompt through two layers: the project's `.phpspec/prompts`
 * directory first (the team's voice, committable, surviving composer updates),
 * then the shipped `Ai/Prompts` directory (package code). A project file fully
 * replaces the shipped one of the same name. Returns an empty string when the
 * artifact is missing everywhere, letting callers fall back.
 *
 * One composition directive keeps shared prose single-sourced: a line of the
 * form `@include <name>` is replaced with that artifact's resolved contents,
 * looked up project-first like any other name. Only a full line is a
 * directive; a missing target resolves to nothing (like any missing
 * artifact); a cycle is an authoring error and throws.
 */
final class PromptLibrary
{
    private readonly Filesystem $projectFilesystem;

    private readonly Filesystem $shippedFilesystem;

    private readonly string $projectDir;

    /**
     * @param Filesystem|null $projectFilesystem reads the project's override files; the project seam
     * @param Filesystem|null $shippedFilesystem reads the shipped prompt files, which are package code
     * @param string|null $projectDir the project root holding .phpspec/prompts; the working directory by default
     */
    public function __construct(?Filesystem $projectFilesystem = null, ?Filesystem $shippedFilesystem = null, ?string $projectDir = null)
    {
        $this->projectFilesystem = $projectFilesystem ?? new RealFilesystem();
        $this->shippedFilesystem = $shippedFilesystem ?? new RealFilesystem();
        $this->projectDir = $projectDir ?? (getcwd() ?: '.');
    }

    /**
     * Returns the contents of the named prompt (`<name>.txt`), project layer
     * first, with its `@include` directives expanded; an empty string when it
     * cannot be read anywhere.
     */
    public function read(string $name): string
    {
        return $this->resolve($name, []);
    }

    /**
     * Every layer the name resolves to, project first, each include-expanded
     * and naming its origin. The one richer question consumers need: the
     * manifest fold and the capture marker both come from it.
     *
     * @return list<Prompt>
     */
    public function stack(string $name): array
    {
        $layers = [];

        $project = $this->projectText($name);
        if ($project !== null) {
            $layers[] = new Prompt($name, $this->expand($project, [$name]), Prompt::PROJECT);
        }

        $shipped = $this->shippedText($name);
        if ($shipped !== null) {
            $layers[] = new Prompt($name, $this->expand($shipped, [$name]), Prompt::SHIPPED);
        }

        return $layers;
    }

    /**
     * Resolves one name to its first existing layer and expands its includes,
     * tracking the chain of names currently being resolved so a cycle fails
     * loudly instead of looping.
     *
     * @param list<string> $resolving the include chain, outermost first
     */
    private function resolve(string $name, array $resolving): string
    {
        if (in_array($name, $resolving, true)) {
            throw new RuntimeException(sprintf('Prompt include cycle: "%s" is included while already being resolved (%s).', $name, implode(' > ', $resolving)));
        }

        $text = $this->projectText($name) ?? $this->shippedText($name) ?? '';
        $resolving[] = $name;

        return $this->expand($text, $resolving);
    }

    /**
     * Expands every full-line `@include <name>` directive in the given text,
     * each target resolved project-first.
     *
     * @param list<string> $resolving the include chain, outermost first
     */
    private function expand(string $text, array $resolving): string
    {
        return (string) preg_replace_callback(
            '~^@include[ \t]+([A-Za-z0-9/_.-]+)[ \t\r]*$~m',
            fn(array $matches): string => trim($this->resolve($matches[1], $resolving)),
            $text,
        );
    }

    /**
     * The raw project override for a name, or null when the project has none.
     */
    private function projectText(string $name): ?string
    {
        $path = $this->projectDir . '/.phpspec/prompts/' . $name . '.txt';

        return $this->projectFilesystem->exists($path) ? $this->projectFilesystem->read($path) : null;
    }

    /**
     * The raw shipped text for a name, or null when the package has none.
     */
    private function shippedText(string $name): ?string
    {
        $path = __DIR__ . '/Prompts/' . $name . '.txt';

        return $this->shippedFilesystem->exists($path) ? $this->shippedFilesystem->read($path) : null;
    }
}
