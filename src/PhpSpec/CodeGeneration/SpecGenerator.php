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
     * Idempotent: an existing spec file is left untouched.
     *
     * @param string $spec the class path using forward slashes (e.g. "App/Model/User")
     * @return bool true when the spec file was created, false when it already existed
     */
    public function generate(string $spec): bool
    {
        $filePath = $this->filePath($spec);

        if ($this->filesystem->exists($filePath)) {
            return false;
        }

        if (!$this->filesystem->exists(dirname($filePath))) {
            $this->filesystem->mkdir(dirname($filePath));
        }
        $this->filesystem->write($filePath, $this->skeleton($spec));

        return true;
    }

    /**
     * Drafts the skeleton spec content for a class path without touching disk:
     * the use statement, an empty-ish describe block, and the instantiation
     * example.
     *
     * @param string $spec the class path using forward slashes
     */
    public function skeleton(string $spec): string
    {
        $pieces = explode('/', $spec);
        $use = '';
        if (count($pieces) > 1) {
            $use = "\n\nuse " . str_replace('/', '\\', $spec) . ';';
        }
        $class = array_pop($pieces);
        $lcClass = lcfirst($class);

        return <<<EOD
        <?php$use

        describe($class::class, function() {
            let("$lcClass", fn() => new $class());
            it("instantiates", fn() => expect(\$this->$lcClass)->toBeAnInstanceOf($class::class));
        });
        EOD;
    }

    /**
     * Drafts the given spec content grown by one it() example for a method,
     * without touching disk. Null when the method is already exemplified or the
     * content has no closing anchor to grow at, so repeated calls never
     * duplicate an example.
     *
     * @param string $content the current spec content
     * @param string $spec the class path using forward slashes
     * @param string $method the method name to exemplify
     */
    public function withExample(string $content, string $spec, string $method): ?string
    {
        // Already exemplified: don't append an identical example again.
        if (str_contains($content, "it(\"should $method\",")) {
            return null;
        }

        $pos = strrpos($content, '});');
        if ($pos === false) {
            return null;
        }

        $pieces = explode('/', $spec);
        $class = array_pop($pieces);
        $lcClass = lcfirst($class);

        $example = "    it(\"should $method\", fn() => expect(\$this->$lcClass->$method())->toBe(null));";

        return substr($content, 0, $pos) . "\n" . $example . "\n" . substr($content, $pos);
    }

    /**
     * The absolute path of the spec file for a class path.
     *
     * @param string $spec the class path using forward slashes
     */
    private function filePath(string $spec): string
    {
        return getcwd() . DIRECTORY_SEPARATOR .
               $this->specPath . DIRECTORY_SEPARATOR .
               str_replace('/', DIRECTORY_SEPARATOR, $spec) .
               $this->specSuffix;
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
        $filePath = $this->filePath($spec);

        if (!$this->filesystem->exists($filePath)) {
            return false;
        }

        $grown = $this->withExample($this->filesystem->read($filePath), $spec, $method);
        if ($grown === null) {
            return false;
        }

        $this->filesystem->write($filePath, $grown);

        return true;
    }
}
