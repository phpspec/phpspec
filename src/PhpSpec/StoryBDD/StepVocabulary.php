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

namespace PhpSpec\StoryBDD;

use PhpSpec\Filesystem;

/**
 * @internal
 * The project's step vocabulary as written on disk: which titles the steps
 * files define, without loading them. Writers consult it before proposing
 * steps content, because a title registers once across all files and a
 * duplicate errors at load.
 */
final class StepVocabulary
{
    private const DEFINITION = '~\b(?:given|when|then|step_and|step_but)\s*\(\s*(["\'])(.*?)\1~';

    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * The definition titles a steps file's content declares, in order.
     *
     * @return list<string>
     */
    public function titlesIn(string $content): array
    {
        preg_match_all(self::DEFINITION, $content, $matches);

        return $matches[2];
    }

    /**
     * Every title defined under the steps roots, mapped to the file defining
     * it (the first, when a legacy tree still holds duplicates).
     *
     * @param string ...$roots absolute paths of the directories holding steps files
     * @return array<string, string> title => absolute steps-file path
     */
    public function definedTitles(string ...$roots): array
    {
        $titles = [];
        foreach ($this->stepsFiles(...$roots) as $file) {
            foreach ($this->titlesIn($this->filesystem->read($file)) as $title) {
                $titles[$title] ??= $file;
            }
        }

        return $titles;
    }

    /**
     * Why proposed steps content must not be written, or null when it may: a
     * title defined twice within it, or a title another steps file already
     * owns. The target file's own titles are exempt, because writing the file
     * replaces them.
     *
     * @param string $content the proposed steps-file content
     * @param string $targetPath the file the content is destined for, absolute or project-relative
     * @param string ...$roots absolute paths of the directories holding steps files
     */
    public function rejectionFor(string $content, string $targetPath, string ...$roots): ?string
    {
        $titles = $this->titlesIn($content);
        $duplicate = $this->firstDuplicate($titles);
        if ($duplicate !== null) {
            return sprintf('The proposed steps define "%s" twice; a step title registers once, so define it once and reuse it.', $duplicate);
        }

        foreach ($this->definedTitles(...$roots) as $title => $file) {
            if (self::isTarget($file, $targetPath)) {
                continue;
            }

            if (in_array($title, $titles, true)) {
                return sprintf('Step "%s" is already defined in "%s"; reuse that step instead of redefining it.', $title, $file);
            }
        }

        return null;
    }

    /**
     * The steps files whose definitions serve the given step texts, in the
     * order their titles are defined, each once: the files a story's steps
     * live in, whatever the files are called.
     *
     * @param list<string> $stepTexts step texts as a feature writes them
     * @param string ...$roots absolute paths of the directories holding steps files
     * @return list<string> absolute steps-file paths
     */
    public function filesServing(array $stepTexts, string ...$roots): array
    {
        $titles = $this->definedTitles(...$roots);
        $registry = new StepRegistry();
        foreach (array_keys($titles) as $title) {
            $registry->addStep($title, static function (): void {});
        }

        $files = [];
        foreach ($stepTexts as $text) {
            $match = $registry->match($text);
            if ($match !== null) {
                $files[$titles[$match->pattern]] = true;
            }
        }

        return array_values(array_intersect(array_unique(array_values($titles)), array_keys($files)));
    }

    /**
     * Every steps file (steps.php or *.steps.php) under the roots, each once.
     *
     * @return list<string> absolute paths
     */
    public function stepsFiles(string ...$roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            foreach ($this->stepsFilesUnder(rtrim($root, '/\\')) as $file) {
                $files[$file] = true;
            }
        }

        return array_keys($files);
    }

    private static function isTarget(string $file, string $targetPath): bool
    {
        $file = str_replace('\\', '/', $file);
        $target = str_replace('\\', '/', $targetPath);

        return $file === $target || str_ends_with($file, '/' . preg_replace('~^\./~', '', $target));
    }

    /**
     * The first title appearing more than once, or null when all are unique.
     *
     * @param list<string> $titles
     */
    private function firstDuplicate(array $titles): ?string
    {
        $seen = [];
        foreach ($titles as $title) {
            if (isset($seen[$title])) {
                return $title;
            }

            $seen[$title] = true;
        }

        return null;
    }

    /**
     * Every steps file (steps.php or *.steps.php) under a directory, recursively.
     *
     * @return list<string> absolute paths
     */
    private function stepsFilesUnder(string $dir): array
    {
        if (!$this->filesystem->isDir($dir)) {
            return [];
        }

        $files = [];
        foreach ($this->filesystem->scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if ($this->filesystem->isDir($path)) {
                $files = array_merge($files, $this->stepsFilesUnder($path));

                continue;
            }

            if ((new StepsFile($entry))->isStepDefinitions()) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
