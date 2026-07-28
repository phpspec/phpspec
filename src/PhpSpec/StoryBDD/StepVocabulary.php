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
     * Every title defined under a features root, mapped to the file defining
     * it (the first, when a legacy tree still holds duplicates).
     *
     * @param string $featuresRoot absolute path of the features directory
     * @return array<string, string> title => absolute steps-file path
     */
    public function definedTitles(string $featuresRoot): array
    {
        $titles = [];
        foreach ($this->stepsFilesUnder($featuresRoot) as $file) {
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
     * @param string $targetPath the file the content is destined for (any base)
     * @param string $featuresRoot absolute path of the features directory
     */
    public function rejectionFor(string $content, string $targetPath, string $featuresRoot): ?string
    {
        $titles = $this->titlesIn($content);
        $duplicate = $this->firstDuplicate($titles);
        if ($duplicate !== null) {
            return sprintf('The proposed steps define "%s" twice; a step title registers once, so define it once and reuse it.', $duplicate);
        }

        $target = basename($targetPath);
        foreach ($this->definedTitles($featuresRoot) as $title => $file) {
            if (basename($file) === $target) {
                continue;
            }

            if (in_array($title, $titles, true)) {
                return sprintf('Step "%s" is already defined in "%s"; reuse that step instead of redefining it.', $title, $file);
            }
        }

        return null;
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
     * Every *.steps.php file under a directory, recursively.
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

            if (str_ends_with($entry, '.steps.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }
}
