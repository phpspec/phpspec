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

/**
 * @internal
 * Loads a named prompt artifact from the Ai/Prompts directory, so the coaching
 * text lives in an editable `.txt` file rather than inline in a command. Returns
 * an empty string when the artifact is missing, letting callers fall back.
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
     * Returns the contents of the named prompt artifact (`<name>.txt`), or an
     * empty string when it cannot be read.
     */
    public function read(string $name): string
    {
        $path = __DIR__ . '/Prompts/' . $name . '.txt';

        return $this->filesystem->exists($path) ? $this->filesystem->read($path) : '';
    }
}
