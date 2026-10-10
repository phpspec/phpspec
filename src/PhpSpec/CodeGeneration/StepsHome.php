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

use PhpSpec\Configuration;
use PhpSpec\Filesystem;
use PhpSpec\ProjectRoot;
use PhpSpec\RealFilesystem;
use PhpSpec\StoryBDD\StepsFile;
use PhpSpec\StoryBDD\StepVocabulary;

/**
 * @internal
 * Where a project keeps its step definitions: the steps directory (steps_path,
 * else the features path's steps/), the default steps.php in it, and the steps
 * files there are. How steps are grouped into files is the project's choice;
 * no file belongs to a feature.
 */
final readonly class StepsHome
{
    private string $root;

    public function __construct(
        private Configuration $config,
        private Filesystem $filesystem = new RealFilesystem(),
        ?string $root = null,
    ) {
        $this->root = rtrim(str_replace('\\', '/', $root ?? (getcwd() ?: '.')), '/');
    }

    /**
     * The steps directory, relative to the project.
     */
    public function directory(): string
    {
        $stepsPath = $this->config->getStepsPath();

        return $stepsPath !== null && trim($stepsPath) !== ''
            ? self::relative($stepsPath)
            : self::relative($this->config->getFeaturesPath()) . '/steps';
    }

    public function defaultFile(): string
    {
        return $this->directory() . '/' . StepsFile::DEFAULT_NAME;
    }

    /**
     * The steps file a name stands for in the steps directory (web, web.php
     * and web.steps.php all mean web.steps.php; steps means steps.php), or
     * null when the name is a path or no name.
     */
    public function file(string $name): ?string
    {
        $name = trim($name);
        $base = match (true) {
            str_ends_with($name, StepsFile::SUFFIX) => substr($name, 0, -strlen(StepsFile::SUFFIX)),
            str_ends_with($name, '.php') => substr($name, 0, -4),
            default => $name,
        };

        if (preg_match('/^[A-Za-z0-9_-]+$/', $base) !== 1) {
            return null;
        }

        return $this->directory() . '/' . ($base === 'steps' ? StepsFile::DEFAULT_NAME : $base . StepsFile::SUFFIX);
    }

    /**
     * The directories searched for steps files: the features root, and the
     * steps directory when it lies outside it.
     *
     * @return list<string> absolute paths
     */
    public function roots(): array
    {
        $features = self::relative($this->config->getFeaturesPath());
        $roots = [$this->root . '/' . $features];

        $steps = $this->directory();
        if ($steps !== $features && !str_starts_with($steps . '/', $features . '/')) {
            $roots[] = $this->root . '/' . $steps;
        }

        return $roots;
    }

    /**
     * The steps files there are, the default first and the rest by path.
     *
     * @return list<string> project-relative paths
     */
    public function files(): array
    {
        $project = ProjectRoot::at($this->root);
        $files = array_map(
            static fn(string $file): string => ltrim($project->relative($file), '/'),
            (new StepVocabulary($this->filesystem))->stepsFiles(...$this->roots()),
        );
        sort($files);

        $default = $this->defaultFile();
        $rest = array_values(array_filter($files, static fn(string $file): bool => $file !== $default));

        return in_array($default, $files, true) ? [$default, ...$rest] : $rest;
    }

    /**
     * A project-relative steps file under the project root.
     */
    public function absolute(string $file): string
    {
        return $this->root . '/' . ltrim(str_replace('\\', '/', $file), '/');
    }

    /**
     * How a steps file is named to a person choosing one: by its name in the
     * steps directory, by its project path anywhere else.
     */
    public function label(string $file): string
    {
        $directory = $this->directory() . '/';

        return str_starts_with($file, $directory) ? substr($file, strlen($directory)) : $file;
    }

    private static function relative(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', trim($path)), '/');

        return preg_replace('~^(\./)+~', '', $path) ?? $path;
    }
}
