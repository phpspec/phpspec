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

namespace PhpSpec\Console\Command\Refactor;

use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;
use PhpSpec\Source\Members;

/**
 * @internal
 * Finds what a refactor run works on: the class named in full, the class by
 * a short name modified last under the source path, the class a spec file
 * describes, or with no target the source modified last under the source
 * path. A target with no spec is refused: nothing would catch a refactoring
 * that broke it.
 */
final readonly class TargetResolver
{
    private string $root;

    public function __construct(
        private Configuration $config,
        private Filesystem $filesystem,
        string $root,
    ) {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @throws UnresolvedTargetException
     */
    public function resolve(?string $target): RefactorTarget
    {
        $target = trim((string) $target);
        $resolved = $target === '' ? $this->modifiedLast() : $this->named($target);

        if (!$this->filesystem->exists($resolved->sourceFile)) {
            throw new UnresolvedTargetException(sprintf('Source file not found: %s', $resolved->sourceFile));
        }

        if (!$this->filesystem->exists($resolved->specFile)) {
            throw new UnresolvedTargetException(sprintf(
                '%s has no spec, so nothing would catch a refactoring that broke it. Describe it first: phpspec describe %s',
                $resolved->fqcn,
                $resolved->fqcn,
            ));
        }

        return $resolved;
    }

    private function modifiedLast(): RefactorTarget
    {
        $newest = (new RecencyScanner($this->filesystem))->mostRecentSource($this->root . '/' . $this->srcPath());

        if ($newest === null) {
            throw new UnresolvedTargetException(sprintf('No source to refactor under %s.', $this->srcPath()));
        }

        $fqcn = Members::in($this->filesystem->read($newest))->classes()[0] ?? null;

        if ($fqcn === null) {
            throw new UnresolvedTargetException(sprintf('%s, the source modified last, declares no class to refactor.', substr($newest, strlen($this->root) + 1)));
        }

        return new RefactorTarget($fqcn, $newest, $this->specFileFor($fqcn));
    }

    private function named(string $target): RefactorTarget
    {
        $suffix = $this->config->getSpecSuffix();

        if (str_ends_with($target, $suffix)) {
            $specFile = str_starts_with($target, '/') ? $target : $this->root . '/' . $target;
            $fqcn = $this->classDescribedBy($specFile, $suffix);

            return new RefactorTarget($fqcn, $this->sourceFileFor($fqcn), $specFile);
        }

        $fqcn = $target;
        $method = null;

        if (str_contains($target, '::')) {
            [$fqcn, $method] = explode('::', $target, 2);
        }

        $fqcn = ltrim($fqcn, '\\');

        if (!str_contains($fqcn, '\\')) {
            $fqcn = $this->classNamed($fqcn);
        }

        return new RefactorTarget($fqcn, $this->sourceFileFor($fqcn), $this->specFileFor($fqcn), $method);
    }

    /**
     * The class a short name stands for: the one by that name modified last
     * under the source path.
     */
    private function classNamed(string $shortName): string
    {
        $newest = (new RecencyScanner($this->filesystem))->mostRecentNamed($this->root . '/' . $this->srcPath(), $shortName . '.php');
        $declared = $newest === null ? [] : Members::in($this->filesystem->read($newest))->classes();

        foreach ($declared as $fqcn) {
            if ($fqcn === $shortName || str_ends_with($fqcn, '\\' . $shortName)) {
                return $fqcn;
            }
        }

        throw new UnresolvedTargetException(sprintf('No class named %s under %s. Describe it first: phpspec describe %s', $shortName, $this->srcPath(), $shortName));
    }

    /**
     * The class a spec file describes, read from where it sits under the spec
     * path: spec/App/Basket.spec.php describes App\Basket.
     */
    private function classDescribedBy(string $specFile, string $suffix): string
    {
        $relative = substr($specFile, strlen($this->root) + 1, -strlen($suffix));
        $specPath = $this->specPath() . '/';

        if (str_starts_with($relative, $specPath)) {
            $relative = substr($relative, strlen($specPath));
        }

        return str_replace('/', '\\', $relative);
    }

    private function sourceFileFor(string $fqcn): string
    {
        return $this->root . '/' . str_replace('\\', '/', $this->config->getSourceLayout()->relativeFileFor($fqcn));
    }

    private function specFileFor(string $fqcn): string
    {
        return $this->root . '/' . $this->specPath() . '/' . str_replace('\\', '/', $fqcn) . $this->config->getSpecSuffix();
    }

    private function srcPath(): string
    {
        return rtrim(ltrim($this->config->getSrcPath(), './'), '/');
    }

    private function specPath(): string
    {
        return rtrim(ltrim($this->config->getSpecPath(), './'), '/');
    }
}
