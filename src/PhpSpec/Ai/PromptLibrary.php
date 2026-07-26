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
 * Loads a named prompt artifact from the Ai/Prompts directory, so the coaching
 * text lives in an editable `.txt` file rather than inline in a command. Returns
 * an empty string when the artifact is missing, letting callers fall back.
 *
 * One composition directive keeps shared prose single-sourced: a line of the
 * form `@include <name>` is replaced with that artifact's resolved contents.
 * Only a full line is a directive; a missing target resolves to nothing (like
 * any missing artifact); a cycle is an authoring error and throws.
 */
final class PromptLibrary
{
    private readonly Filesystem $filesystem;

    /**
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     */
    public function __construct(?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Returns the contents of the named prompt artifact (`<name>.txt`) with its
     * `@include` directives expanded, or an empty string when it cannot be read.
     */
    public function read(string $name): string
    {
        return $this->resolve($name, []);
    }

    /**
     * Reads one artifact and expands its include directives, tracking the chain
     * of names currently being resolved so a cycle fails loudly instead of
     * looping.
     *
     * @param list<string> $resolving the include chain, outermost first
     */
    private function resolve(string $name, array $resolving): string
    {
        if (in_array($name, $resolving, true)) {
            throw new RuntimeException(sprintf('Prompt include cycle: "%s" is included while already being resolved (%s).', $name, implode(' > ', $resolving)));
        }

        $path = __DIR__ . '/Prompts/' . $name . '.txt';
        $text = $this->filesystem->exists($path) ? $this->filesystem->read($path) : '';

        $resolving[] = $name;

        return (string) preg_replace_callback(
            '~^@include[ \t]+([A-Za-z0-9/_.-]+)[ \t\r]*$~m',
            fn(array $matches): string => trim($this->resolve($matches[1], $resolving)),
            $text,
        );
    }
}
