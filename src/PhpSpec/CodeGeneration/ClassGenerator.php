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
use PhpSpec\RealFilesystem;
use RuntimeException;

/**
 * @internal
 * Generates PHP class files from a fully qualified class name.
 * Creates the directory structure and writes a minimal class skeleton.
 */
final class ClassGenerator
{
    private readonly Filesystem $filesystem;

    /**
     * @param SourceLayout $layout where a class's file lives
     * @param Filesystem $filesystem filesystem abstraction for testability
     */
    public function __construct(private readonly SourceLayout $layout = new SourceLayout(), ?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Resolves a FQCN into its short name, namespace declaration, and file path
     * under one source directory with one PSR-4 prefix.
     *
     * @return array{shortName: string, namespace: string, filePath: string}
     */
    public static function resolveFqcn(string $fqcn, string $srcPath, string $psr4Prefix = ''): array
    {
        return SourceLayout::under($srcPath, $psr4Prefix)->locate($fqcn);
    }

    /**
     * Generates a class file for the given fully qualified class name.
     *
     * @param string $fqcn the fully qualified class name (e.g. "App\\Model\\User")
     * @return string confirmation message with the generated file path
     * @throws RuntimeException if the class file already exists
     */
    public function generate(string $fqcn): string
    {
        ['shortName' => $className, 'namespace' => $namespace, 'filePath' => $filePath] = $this->layout->locate($fqcn);

        $this->generateClass($className, $filePath, $namespace);

        return sprintf('Class %s generated in %s', $fqcn, ProjectRoot::here()->relative($filePath));
    }

    /**
     * Writes a class skeleton to the given file path with the specified namespace.
     *
     * @param string $className the short class name
     * @param string $filePath the absolute path to write the file to
     * @param string $namespace the namespace declaration string (including "namespace" keyword)
     * @throws RuntimeException if the file already exists
     */
    public function generateClass(string $className, string $filePath, string $namespace): void
    {
        $classContent = implode("\n", [
            '<?php' . $namespace,
            '',
            'class ' . $className,
            '{',
            '}',
            '',
        ]);

        if ($this->filesystem->exists($filePath)) {
            throw new RuntimeException(sprintf('Class %s already exists in %s.', $className, ProjectRoot::here()->relative($filePath)));
        }

        if (!$this->filesystem->exists(dirname($filePath))) {
            $this->filesystem->mkdir(dirname($filePath));
        }
        $this->filesystem->write($filePath, $classContent);
    }
}
