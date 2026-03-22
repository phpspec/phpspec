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

namespace PhpSpec;

use PhpSpec\EventDispatcher\DispatcherRegistry;
use PhpSpec\EventDispatcher\Subscriber\SpecificationSubscriber;
use PhpSpec\Specification\SpecBlock;
use PhpSpec\StoryBDD\Feature;
use PhpSpec\StoryBDD\GherkinParser;
use StoryBDDRegistry;

/**
 * Scans directories for spec and feature files and builds a Suite from them.
 */
final class Loader
{
    /**
     * @param Filesystem|null $filesystem injectable filesystem for testability
     * @param string $specSuffix file suffix used to identify spec files
     */
    public function __construct(private ?Filesystem $filesystem = null, private string $specSuffix = '.spec.php')
    {
        $this->filesystem ??= new RealFilesystem();
    }

    /**
     * Loads spec or feature files from the given path and returns a Suite.
     *
     * @param string|null $files directory or file path; defaults to ./spec
     * @param string|null $filter substring to match against spec file paths
     */
    public function load(?string $files, ?string $filter = null): Suite
    {
        if (!$files) {
            $files = './spec';
        }

        $paths = array_map('trim', explode(',', $files));
        $blocks = [];

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }
            if ($this->isFeaturePath($path)) {
                $blocks = array_merge($blocks, $this->loadFeatures($path));
            } else {
                $blocks = array_merge($blocks, $this->loadSuite($path));
            }
        }

        if ($filter) {
            $blocks = array_values(array_filter($blocks, function (SpecBlock $block) use ($filter) {
                if ($block instanceof Specification) {
                    return stripos($block->getPath(), $filter) !== false;
                }
                if ($block instanceof Feature) {
                    return stripos($block->getPath(), $filter) !== false;
                }
                return true;
            }));
        }

        return new Suite($blocks);
    }

    /**
     * Determines whether the path points to Gherkin feature files.
     *
     * @param string $path filesystem path to inspect
     */
    private function isFeaturePath(string $path): bool
    {
        return str_ends_with($path, '.feature')
            || str_contains($path, 'features');
    }

    /**
     * Recursively scans a directory for *.spec.php files and creates Specification objects.
     *
     * @param string $directory root directory to scan
     * @return array<Specification>
     */
    private function loadSuite(string $directory): array
    {
        $specifications = [];

        if (!$this->filesystem->isFile($directory) && !$this->filesystem->isDir($directory)) {
            return [];
        }

        if ($this->filesystem->isFile($directory) && str_ends_with($directory, $this->specSuffix)) {
            return [$this->loadFile($directory)];
        }

        $files = $this->filesystem->scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $directory . '/' . $file;

            if ($this->filesystem->isDir($filePath)) {
                $subdirectoryFilePaths = $this->loadSuite($filePath);
                $specifications = array_merge($specifications, $subdirectoryFilePaths);
            } elseif ($this->filesystem->isFile($filePath) && str_ends_with($file, $this->specSuffix)) {
                $specifications[] = $this->loadFile($filePath);
            }
        }

        return $specifications;
    }

    /**
     * Creates a Specification from a single spec file and registers its subscriber.
     *
     * @param string $file path to a *.spec.php file
     */
    private function loadFile(string $file): Specification
    {
        $spec = new Specification($file, $this->specSuffix);
        DispatcherRegistry::dispatcher()->addSubscriber(new SpecificationSubscriber($spec));
        return $spec;
    }

    /**
     * Parses Gherkin feature files and loads associated step definitions.
     *
     * @param string $path directory or file containing .feature files
     * @return array<\PhpSpec\StoryBDD\Feature>
     */
    private function loadFeatures(string $path): array
    {
        $parser = new GherkinParser();
        $featureFiles = [];
        $stepFiles = [];

        $this->scanFeatures($path, $featureFiles, $stepFiles);

        // Fresh registry so prior spec execution can't pollute step definitions
        StoryBDDRegistry::init();

        // Load step definitions into the fresh registry
        foreach ($stepFiles as $stepFile) {
            require $stepFile;
        }

        $features = [];
        foreach ($featureFiles as $featureFile) {
            $content = $this->filesystem->read($featureFile);
            $featureNode = $parser->parse($content);
            $features[] = new Feature(
                $featureFile,
                $featureNode,
                StoryBDDRegistry::$steps,
                StoryBDDRegistry::$hooks,
            );
        }

        return $features;
    }

    /**
     * Recursively discovers .feature files and step definition files.
     *
     * @param string $path directory or file to scan
     * @param array $featureFiles collected feature file paths (by reference)
     * @param array $stepFiles collected step definition paths (by reference)
     */
    private function scanFeatures(string $path, array &$featureFiles, array &$stepFiles): void
    {
        if ($this->filesystem->isFile($path) && str_ends_with($path, '.feature')) {
            $featureFiles[] = $path;
            // Walk up from the feature file's directory to find steps/ dirs
            $dir = dirname($path);
            while ($dir !== dirname($dir)) {
                $stepsDir = $dir . '/steps';
                if ($this->filesystem->isDir($stepsDir)) {
                    $this->collectStepFiles($stepsDir, $stepFiles);
                }
                $dir = dirname($dir);
            }
            return;
        }

        if (!$this->filesystem->isDir($path)) {
            return;
        }

        $files = $this->filesystem->scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . '/' . $file;

            if ($this->filesystem->isDir($filePath)) {
                if ($file === 'steps') {
                    $this->collectStepFiles($filePath, $stepFiles);
                } else {
                    $this->scanFeatures($filePath, $featureFiles, $stepFiles);
                }
            } elseif (str_ends_with($file, '.feature')) {
                $featureFiles[] = $filePath;
            }
        }
    }

    /**
     * Recursively collects *.steps.php files from a steps directory.
     *
     * @param string $dir steps directory to scan
     * @param array $stepFiles collected step file paths (by reference)
     */
    private function collectStepFiles(string $dir, array &$stepFiles): void
    {
        $files = $this->filesystem->scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $dir . '/' . $file;
            if ($this->filesystem->isDir($filePath)) {
                $this->collectStepFiles($filePath, $stepFiles);
            } elseif ($this->filesystem->isFile($filePath) && str_ends_with($file, '.steps.php')) {
                $stepFiles[] = $filePath;
            }
        }
    }
}
