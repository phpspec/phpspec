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

use PhpSpec\Filesystem;
use PhpSpec\RealFilesystem;

/**
 * @internal
 * The single write gate of the agent pipeline: tools only propose, the
 * presenter confirms, and this applies. Nothing else in the pipeline touches
 * disk.
 */
final class Writer
{
    private readonly Filesystem $filesystem;

    /**
     * @param Filesystem|null $filesystem filesystem abstraction for testability
     * @param string|null $baseDir the project dir to write under, defaulting to the cwd
     */
    public function __construct(?Filesystem $filesystem = null, private readonly ?string $baseDir = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Writes a confirmed proposal to disk, creating its directory when needed.
     */
    public function apply(Proposal $proposal): void
    {
        $file = ($this->baseDir ?? (getcwd() ?: '.')) . '/' . $proposal->path;
        $dir = dirname($file);
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }

        $this->filesystem->write($file, $proposal->new);
    }
}
