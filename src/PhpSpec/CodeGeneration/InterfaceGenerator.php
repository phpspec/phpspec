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
 * Generates PHP interface files from a fully qualified interface name.
 * Creates the directory structure and writes a minimal interface skeleton.
 */
final class InterfaceGenerator
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
     * Generates an interface file for the given fully qualified name.
     *
     * @param string $fqcn the fully qualified interface name (e.g. "App\\Contract\\Repository")
     * @return string confirmation message with the generated file path
     * @throws RuntimeException if the interface file already exists
     */
    public function generate(string $fqcn): string
    {
        ['shortName' => $interfaceName, 'namespace' => $namespace, 'filePath' => $filePath] = ClassGenerator::resolveFqcn($fqcn, $this->srcPath);

        $content = <<<EOD
        <?php$namespace

        interface {$interfaceName}
        {

        }
        EOD;

        if (!$this->filesystem->exists($filePath)) {
            if (!$this->filesystem->exists(dirname($filePath))) {
                $this->filesystem->mkdir(dirname($filePath));
            }
            $this->filesystem->write($filePath, $content);
            return "Interface '$interfaceName' generated at '$filePath'\n";
        } else {
            throw new RuntimeException("Interface '$interfaceName' already exists at '$filePath'\n");
        }
    }
}
