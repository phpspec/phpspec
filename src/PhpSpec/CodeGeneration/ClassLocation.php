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
use PhpSpec\ProjectRoot;
use ReflectionClass;

/**
 * @internal
 * Where a class lives and whether it is really there. A class that is loaded
 * lives where it was loaded from; one that is not yet written lives where the
 * layout (source directory and PSR-4 prefix) says it will. Distinguishes "the
 * file exists" from "the class loads", so a runtime class-not-found (an
 * autoload/PSR-4 mismatch) is never mistaken for a missing source file.
 */
final readonly class ClassLocation
{
    private function __construct(
        private string $fqcn,
        private string $filePath,
    ) {}

    /**
     * The file a loaded class came from, when it is one of the project's own;
     * otherwise where the layout would put the class. A missing method on a
     * vendor or internal class must never be written into vendor/.
     */
    public static function for(string $fqcn, string $srcPath, string $psr4Prefix = ''): self
    {
        return new self($fqcn, self::loadedFrom($fqcn) ?? ClassGenerator::resolveFqcn($fqcn, $srcPath, $psr4Prefix)['filePath']);
    }

    private static function loadedFrom(string $fqcn): ?string
    {
        if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn)) {
            return null;
        }

        $file = (new ReflectionClass($fqcn))->getFileName();
        $root = ProjectRoot::here();

        if ($file === false || !$root->holds($file) || str_starts_with($root->relative($file), 'vendor/')) {
            return null;
        }

        return $file;
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
