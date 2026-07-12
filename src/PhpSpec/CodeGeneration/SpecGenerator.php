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

/**
 * @internal
 * Generates spec files with describe/it boilerplate for a given class.
 * Creates spec files using the Jasmine/RSpec-style DSL with a basic instantiation example.
 */
final class SpecGenerator
{
    private readonly Filesystem $filesystem;

    /**
     * @param string $specPath relative path to the spec directory
     * @param Filesystem $filesystem filesystem abstraction for testability
     * @param string $specSuffix file suffix for spec files (e.g. '.spec.php')
     */
    public function __construct(private readonly string $specPath = 'spec', ?Filesystem $filesystem = null, private string $specSuffix = '.spec.php')
    {
        $this->filesystem = $filesystem ?? new RealFilesystem();
    }

    /**
     * Returns the configured spec directory path.
     *
     * @return string the spec path
     */
    public function getSpecPath(): string
    {
        return $this->specPath;
    }

    /**
     * Returns the file suffix used for spec files (e.g. '.spec.php').
     *
     * @return string the spec file suffix
     */
    public function getSpecSuffix(): string
    {
        return $this->specSuffix;
    }

    /**
     * Generates a spec file for the given class path with a describe block and basic example.
     *
     * @param string $spec the class path using forward slashes (e.g. "App/Model/User")
     * @return void
     */
    public function generate(string $spec): void
    {
        $filePath = getcwd() . DIRECTORY_SEPARATOR .
                    $this->specPath . DIRECTORY_SEPARATOR .
                    str_replace('/', DIRECTORY_SEPARATOR, $spec) .
                    $this->specSuffix;

        $pieces = explode('/', $spec);
        $use = '';
        if (count($pieces) > 1) {
            $use = "\n\nuse " . str_replace('/', '\\', $spec) . ';';
        }
        $class = array_pop($pieces);
        $lcClass = lcfirst($class);

        $specContent = <<<EOD
        <?php$use

        describe($class::class, function() {
            let("$lcClass", fn() => new $class());
            it("instantiates", fn() => expect(\$this->$lcClass)->toBeAnInstanceOf($class::class));
        });
        EOD;

        if (!$this->filesystem->exists($filePath)) {
            if (!$this->filesystem->exists(dirname($filePath))) {
                $this->filesystem->mkdir(dirname($filePath));
            }
            $this->filesystem->write($filePath, $specContent);
        }
    }

    /**
     * Appends an it() example for a specific method to an existing spec file.
     *
     * Idempotent: if an example for the same method is already present, the
     * file is left untouched so repeated calls never duplicate the example.
     *
     * @param string $spec the class path using forward slashes
     * @param string $method the method name to exemplify
     * @return bool true when an example was added, false when nothing changed
     */
    public function addExample(string $spec, string $method): bool
    {
        $filePath = getcwd() . DIRECTORY_SEPARATOR .
                    $this->specPath . DIRECTORY_SEPARATOR .
                    str_replace('/', DIRECTORY_SEPARATOR, $spec) .
                    $this->specSuffix;

        if (!$this->filesystem->exists($filePath)) {
            return false;
        }

        $content = $this->filesystem->read($filePath);

        // Already exemplified — don't append an identical example again.
        if (str_contains($content, "it(\"should $method\",")) {
            return false;
        }

        $pos = strrpos($content, '});');
        if ($pos === false) {
            return false;
        }

        $pieces = explode('/', $spec);
        $class = array_pop($pieces);
        $lcClass = lcfirst($class);

        $example = "    it(\"should $method\", fn() => expect(\$this->$lcClass->$method())->toBe(null));";

        $content = substr($content, 0, $pos) . "\n" . $example . "\n" . substr($content, $pos);
        $this->filesystem->write($filePath, $content);

        return true;
    }
}
