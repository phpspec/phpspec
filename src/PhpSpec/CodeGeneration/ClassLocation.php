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

use PhpSpec\Filesystem;

/**
 * @internal
 * Where a class lives and whether it is really there. Resolves an FQCN to its
 * source file via the one canonical resolver (ClassGenerator::resolveFqcn) and
 * distinguishes "the file exists" from "the class loads" — so a runtime
 * class-not-found (an autoload/PSR-4 mismatch) is never mistaken for a missing
 * source file.
 */
final readonly class ClassLocation
{
    private function __construct(
        private string $fqcn,
        private string $filePath,
    ) {}

    /**
     * Resolves the location of a class from its FQCN, the source directory, and
     * the PSR-4 prefix mapped to it — the same resolution the generator writes to.
     */
    public static function for(string $fqcn, string $srcPath, string $psr4Prefix = ''): self
    {
        return new self($fqcn, ClassGenerator::resolveFqcn($fqcn, $srcPath, $psr4Prefix)['filePath']);
    }

    /**
     * The absolute path where the class's source file would live.
     */
    public function filePath(): string
    {
        return $this->filePath;
    }

    /**
     * Whether the source file exists on disk (regardless of autoloading).
     */
    public function exists(Filesystem $filesystem): bool
    {
        return $filesystem->exists($this->filePath);
    }

    /**
     * Whether the class/interface/trait actually loads (an autoload success),
     * which is a different question from whether the file exists.
     */
    public function isAutoloadable(): bool
    {
        return class_exists($this->fqcn) || interface_exists($this->fqcn) || trait_exists($this->fqcn);
    }
}
