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

use PhpSpec\Specification\SpecBlock;
use PhpSpec\StoryBDD\Feature;
use PhpSpec\StoryBDD\FeatureNode;
use PhpSpec\StoryBDD\GherkinParser;
use PhpSpec\StoryBDD\ScenarioLineSelector;
use PhpSpec\StoryBDD\StoryBDDRegistry;

/**
 * @internal
 * Scans directories for spec and feature files and builds a Suite from them.
 */
final class Loader
{
    /**
     * @param Filesystem|null $filesystem injectable filesystem for testability
     * @param string $specSuffix file suffix used to identify spec files
     * @param string $featuresPath the features root; step definitions anywhere under it are discoverable
     * @param string|null $stepsPath additional step definitions directory, or null when not configured
     */
    private Filesystem $fs;

    public function __construct(
        ?Filesystem $filesystem = null,
        private string $specSuffix = '.spec.php',
        private string $featuresPath = './features',
        private ?string $stepsPath = null,
    ) {
        $this->fs = $filesystem ?? new RealFilesystem();
    }

    /**
     * Loads spec or feature files from the given path and returns a Suite.
     *
     * @param string|null $files directory or file path; defaults to ./spec
     * @param string|null $filter substring to match against spec file paths
     *
     * @throws \RuntimeException when a steps file registers a step title that is already defined
     */
    public function load(?string $files, ?string $filter = null): Suite
    {
        if (!$files) {
            $files = './spec';
        }

        $blocks = [];

        foreach ($this->targets($files) as $path => $lines) {
            if ($this->isFeaturePath($path)) {
                $blocks = array_merge($blocks, $this->loadFeatures($path, $lines));
            } else {
                LineTargetRegistry::add($path, ...$lines);
                $blocks = array_merge($blocks, $this->loadSuite($path));
            }
        }

        if ($filter) {
            $blocks = $this->filterBlocks($blocks, $filter);
        }

        return new Suite($blocks);
    }

    /**
     * The paths to load, each with the lines its "path:LINE" selectors
     * target. A path asked for whole has no lines, and a selector on it does
     * not narrow it.
     *
     * @param string $files the comma-separated paths
     * @return array<string, list<int>>
     */
    private function targets(string $files): array
    {
        $targets = [];
        $whole = [];

        foreach (array_map('trim', explode(',', $files)) as $path) {
            if ($path === '') {
                continue;
            }

            if (preg_match('/^(.+):(\d+)$/', $path, $matches) !== 1
                || (!str_ends_with($matches[1], '.feature') && !str_ends_with($matches[1], $this->specSuffix))
            ) {
                $whole[$path] = true;
                $targets[$path] = [];

                continue;
            }

            if (!isset($whole[$matches[1]])) {
                $targets[$matches[1]][] = (int) $matches[2];
            }
        }

        return $targets;
    }

    /**
     * Filters spec blocks by path or title match.
     *
     * Blocks whose path matches are kept whole. Features are otherwise
     * reduced to the scenarios whose title matches, and dropped when none
     * does. Specifications are always kept: example titles are only known
     * at run time, so they prune themselves as they run.
     *
     * @param array<SpecBlock> $blocks blocks to filter
     * @param string $filter the --filter text
     * @return array<SpecBlock>
     */
    private function filterBlocks(array $blocks, string $filter): array
    {
        $titleFilter = new TitleFilter($filter);
        $filtered = [];

        foreach ($blocks as $block) {
            $path = $this->getBlockPath($block);

            if ($path === null || $titleFilter->matchesPath($path)) {
                $filtered[] = $block;
                continue;
            }

            if ($block instanceof Feature) {
                $reduced = $block->withScenariosMatching($titleFilter);

                if ($reduced !== null) {
                    $filtered[] = $reduced;
                }
                continue;
            }

            $filtered[] = $block;
        }

        return $filtered;
    }

    /**
     * Extracts the file path from a spec block, if available.
     */
    private function getBlockPath(SpecBlock $block): ?string
    {
        if ($block instanceof \PhpSpec\Specification) {
            return $block->getPath();
        }
        if ($block instanceof Feature) {
            return $block->getPath();
        }
        return null;
    }

    /**
     * Determines whether the path points to Gherkin feature files.
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

        if (!$this->fs->isFile($directory) && !$this->fs->isDir($directory)) {
            return [];
        }

        if ($this->fs->isFile($directory) && str_ends_with($directory, $this->specSuffix)) {
            return [$this->loadFile($directory)];
        }

        $files = $this->fs->scandir($directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = self::join($directory, $file);

            if ($this->fs->isDir($filePath)) {
                $subdirectoryFilePaths = $this->loadSuite($filePath);
                $specifications = array_merge($specifications, $subdirectoryFilePaths);
            } elseif ($this->fs->isFile($filePath) && str_ends_with($file, $this->specSuffix)) {
                $specifications[] = $this->loadFile($filePath);
            }
        }

        return $specifications;
    }

    /**
     * Creates a Specification from a single spec file.
     *
     * @param string $file path to a *.spec.php file
     */
    private function loadFile(string $file): Specification
    {
        return new Specification($file, $this->specSuffix);
    }

    /**
     * Parses Gherkin feature files and loads associated step definitions.
     * When lines are targeted, each feature is reduced to the scenarios they
     * address.
     *
     * @param string $path directory or file containing .feature files
     * @param list<int> $lines the line numbers from "file.feature:LINE" paths, none to load all scenarios
     * @return array<\PhpSpec\StoryBDD\Feature>
     */
    private function loadFeatures(string $path, array $lines = []): array
    {
        $parser = new GherkinParser();
        $featureFiles = [];
        $stepFiles = [];

        $this->scanFeatures($path, $featureFiles, $stepFiles);

        // Step definitions are discoverable anywhere under the features root
        // and in the configured steps directory, wherever the run points
        $featuresRoot = rtrim($this->featuresPath, '/');

        if ($this->fs->isDir($featuresRoot)) {
            $this->collectStepFiles($featuresRoot, $stepFiles);
        }

        if ($this->stepsPath !== null && $this->fs->isDir($this->stepsPath)) {
            $this->collectStepFiles($this->stepsPath, $stepFiles);
        }

        // Helper classes for steps, the Cucumber way: everything under
        // <features>/support loads before any step file needs it.
        $this->loadSupport($featuresRoot);

        // Fresh registry so prior spec execution can't pollute step definitions
        StoryBDDRegistry::init();

        // Load step definitions into the fresh registry; ancestor and in-tree
        // discovery can both find the same steps directory, so deduplicate.
        // Dedupe by realpath, not the raw string: the same file can be
        // reached through paths that differ only in slashes (e.g. a
        // trailing slash on the run path yields "features//steps/x.php"
        // alongside "features/steps/x.php"), which array_unique wouldn't catch.
        $uniqueStepFiles = [];
        foreach ($stepFiles as $stepFile) {
            $uniqueStepFiles[realpath($stepFile) ?: $stepFile] = $stepFile;
        }

        foreach ($uniqueStepFiles as $stepFile) {
            require $stepFile;
        }

        $features = [];
        foreach ($featureFiles as $featureFile) {
            $content = $this->fs->read($featureFile);
            $featureNode = $parser->parse($content, $featureFile);

            if ($lines !== []) {
                $featureNode = new FeatureNode(
                    $featureNode->title,
                    $featureNode->description,
                    $featureNode->background,
                    ScenarioLineSelector::select($featureNode->scenarios, ...$lines),
                    $featureNode->tags,
                );
            }

            $features[] = new Feature(
                $featureFile,
                $featureNode,
                StoryBDDRegistry::steps(),
                StoryBDDRegistry::hooks(),
            );
        }

        return $features;
    }

    /**
     * Requires every PHP file under <features>/support, once per process:
     * support files define classes, and a class cannot be declared twice.
     */
    private function loadSupport(string $featuresRoot): void
    {
        $supportDir = $featuresRoot . '/support';
        if (!$this->fs->isDir($supportDir)) {
            return;
        }

        foreach ($this->collectPhpFiles($supportDir) as $file) {
            require_once $file;
        }
    }

    /**
     * @return list<string>
     */
    private function collectPhpFiles(string $dir): array
    {
        $files = [];
        foreach ($this->fs->scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if ($this->fs->isDir($path)) {
                $files = array_merge($files, $this->collectPhpFiles($path));
            } elseif (str_ends_with($entry, '.php')) {
                $files[] = $path;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Recursively discovers .feature files and step definition files.
     *
     * @param string $path directory or file to scan
     * @param array<string> $featureFiles collected feature file paths (by reference)
     * @param array<string> $stepFiles collected step definition paths (by reference)
     */
    private function scanFeatures(string $path, array &$featureFiles, array &$stepFiles): void
    {
        if ($this->fs->isFile($path) && str_ends_with($path, '.feature')) {
            $featureFiles[] = $path;
            $this->collectAncestorSteps(dirname($path), $stepFiles);

            return;
        }

        if (!$this->fs->isDir($path)) {
            return;
        }

        // Steps may live beside an ancestor (e.g. features/steps/ when
        // running features/scenarios/checkout), not only inside the tree
        $this->collectAncestorSteps($path, $stepFiles);

        $files = $this->fs->scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = self::join($path, $file);

            if ($this->fs->isDir($filePath)) {
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
     * Joins a directory and one of its entries. A directory named on the command
     * line usually carries a trailing slash ("features/"), and a doubled
     * separator would travel from here into every path PhpSpec reports back,
     * including the rerun commands an agent is meant to run verbatim.
     *
     * @param string $directory the containing directory, with or without a trailing separator
     * @param string $entry the file or directory name inside it
     */
    private static function join(string $directory, string $entry): string
    {
        return rtrim($directory, '/\\') . '/' . $entry;
    }

    /**
     * Walks up from a directory collecting step files from every steps/
     * directory found beside it or its ancestors.
     *
     * @param string $dir the directory to walk up from
     * @param array<string> $stepFiles collected step file paths (by reference)
     */
    private function collectAncestorSteps(string $dir, array &$stepFiles): void
    {
        while ($dir !== dirname($dir)) {
            $stepsDir = $dir . '/steps';

            if ($this->fs->isDir($stepsDir)) {
                $this->collectStepFiles($stepsDir, $stepFiles);
            }

            $dir = dirname($dir);
        }
    }

    /**
     * Recursively collects *.steps.php files from a steps directory.
     *
     * @param string $dir steps directory to scan
     * @param array<string> $stepFiles collected step file paths (by reference)
     */
    private function collectStepFiles(string $dir, array &$stepFiles): void
    {
        $files = $this->fs->scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = self::join($dir, $file);
            if ($this->fs->isDir($filePath)) {
                $this->collectStepFiles($filePath, $stepFiles);
            } elseif ($this->fs->isFile($filePath) && str_ends_with($file, '.steps.php')) {
                $stepFiles[] = $filePath;
            }
        }
    }
}
