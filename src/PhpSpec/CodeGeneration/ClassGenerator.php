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
     * @param string $srcPath relative path to the source directory
     * @param Filesystem $filesystem filesystem abstraction for testability
     */
    public function __construct(private readonly string $srcPath = 'src', ?Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Resolves a FQCN into its short name, namespace declaration, and file path.
     *
     * @param string $fqcn the fully qualified class or interface name
     * @param string $srcPath relative path to the source directory
     * @return array{shortName: string, namespace: string, filePath: string}
     */
    public static function resolveFqcn(string $fqcn, string $srcPath): array
    {
        $pieces = explode('\\', $fqcn);
        $shortName = $pieces[count($pieces) - 1];
        $namespace = '';
        $filePath = getcwd() . DIRECTORY_SEPARATOR . $srcPath . DIRECTORY_SEPARATOR;

        if (count($pieces) > 1) {
            $namespace = "\n\nnamespace " . implode('\\', array_slice($pieces, 0, count($pieces) - 1)) . ';';
            $filePath .= implode(DIRECTORY_SEPARATOR, array_slice($pieces, 0, -1)) . DIRECTORY_SEPARATOR . $shortName . '.php';
        } else {
            $filePath .= $shortName . '.php';
        }

        return ['shortName' => $shortName, 'namespace' => $namespace, 'filePath' => $filePath];
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
        ['shortName' => $className, 'namespace' => $namespace, 'filePath' => $filePath] = self::resolveFqcn($fqcn, $this->srcPath);

        return $this->generateClass($className, $filePath, $namespace);
    }

    /**
     * Writes a class skeleton to the given file path with the specified namespace.
     *
     * @param string $className the short class name
     * @param string $filePath the absolute path to write the file to
     * @param string $namespace the namespace declaration string (including "namespace" keyword)
     * @return string confirmation message
     * @throws RuntimeException if the file already exists
     */
    public function generateClass(string $className, string $filePath, string $namespace): string
    {
        $classContent = <<<EOD
        <?php$namespace

        class {$className}
        {

        }
        EOD;

        if (!$this->filesystem->exists($filePath)) {
            if (!$this->filesystem->exists(dirname($filePath))) {
                $this->filesystem->mkdir(dirname($filePath));
            }
            $this->filesystem->write($filePath, $classContent);
            return "Class '$className' generated at '$filePath'\n";
        } else {
            throw new RuntimeException("Class '$className' already exists at '$filePath'\n");
        }
    }
}
