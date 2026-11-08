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

use PhpSpec\CommandLine;
use PhpSpec\Configuration;
use PhpSpec\Console\Command\Run\RecencyScanner;
use PhpSpec\Filesystem;
use PhpSpec\Source\Members;

/**
 * @internal
 * Finds what a refactor run works on: the class named in full (with
 * backslashes or slashes), the class by a short name modified last under the
 * source path, the class a source file declares or a spec file describes, or
 * with no target the source modified last under the source path. A target
 * with no spec is refused: nothing would catch a refactoring that broke it.
 */
final readonly class TargetResolver
{
    private string $root;

    private CommandLine $commandLine;

    /**
     * @param CommandLine|null $commandLine how the describe a refusal suggests is typed; the one this process was started by when not given
     */
    public function __construct(
        private Configuration $config,
        private Filesystem $filesystem,
        string $root,
        ?CommandLine $commandLine = null,
    ) {
        $this->root = rtrim($root, '/\\');
        $this->commandLine = $commandLine ?? CommandLine::invoked($config);
    }

    /**
     * @throws UnresolvedTargetException
     */
    public function resolve(?string $target): RefactorTarget
    {
        $target = trim((string) $target);
        $resolved = $target === '' ? $this->modifiedLast() : $this->named($target);

        if (!$this->filesystem->exists($resolved->sourceFile)) {
            throw new UnresolvedTargetException(
                sprintf('No class %s at %s.', $resolved->fqcn, $this->relative($resolved->sourceFile)),
                'Describe it first: ' . $this->commandLine->for('describe', str_replace('\\', '/', $resolved->fqcn)),
            );
        }

        $members = Members::in($this->filesystem->read($resolved->sourceFile));
        $kind = $members->kindOf($resolved->fqcn) ?? 'class';

        if ($kind === 'interface') {
            throw new UnresolvedTargetException(sprintf('%s is an interface, with no behaviour of its own to refactor.', $resolved->fqcn), 'Refactor a class that implements it.');
        }

        if ($resolved->method !== null && !$members->declares($resolved->fqcn, $resolved->method)) {
            throw new UnresolvedTargetException(sprintf('%s has no method %s().', $resolved->fqcn, $resolved->method));
        }

        if (!$this->filesystem->exists($resolved->specFile)) {
            throw new UnresolvedTargetException(
                sprintf('%s has no spec, so nothing would catch a refactoring that broke it.', $resolved->fqcn),
                $kind === 'class'
                    ? 'Describe it first: ' . $this->commandLine->for('describe', str_replace('\\', '/', $resolved->fqcn))
                    : sprintf('Write its spec first: %s', $this->relative($resolved->specFile)),
            );
        }

        return $resolved;
    }

    private function modifiedLast(): RefactorTarget
    {
        $newest = (new RecencyScanner($this->filesystem))->mostRecentSource(...$this->sourceDirectories());

        if ($newest === null) {
            throw new UnresolvedTargetException(sprintf('No source to refactor under %s.', $this->sourceRootsNamed()));
        }

        $fqcn = Members::in($this->filesystem->read($newest))->classes()[0] ?? null;

        if ($fqcn === null) {
            throw new UnresolvedTargetException(sprintf('%s, the source modified last, declares no class to refactor.', $this->relative($newest)));
        }

        return new RefactorTarget($fqcn, $newest, $this->specFileFor($fqcn));
    }

    private function named(string $target): RefactorTarget
    {
        $suffix = $this->config->getSpecSuffix();
        $path = (string) preg_replace('~^(\./)+~', '', str_replace('\\', '/', $target));

        if (str_ends_with($path, $suffix)) {
            $specFile = $this->absolute($path);
            $fqcn = $this->classDescribedBy($specFile, $suffix);

            return new RefactorTarget($fqcn, $this->sourceFileFor($fqcn), $specFile);
        }

        if (str_ends_with($path, '.php')) {
            return $this->declaredIn($this->absolute($path));
        }

        [$fqcn, $method] = str_contains($path, '::') ? explode('::', $path, 2) : [$path, ''];
        $fqcn = ltrim(str_replace('/', '\\', $fqcn), '\\');

        if (preg_match('~^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$~', $fqcn) !== 1) {
            throw new UnresolvedTargetException(sprintf('Not a class name: %s.', $target), 'Name a class (App/Till), its source file or its spec file.');
        }

        if (!str_contains($fqcn, '\\')) {
            $fqcn = $this->classNamed($fqcn);
        }

        return new RefactorTarget($fqcn, $this->sourceFileFor($fqcn), $this->specFileFor($fqcn), $method === '' ? null : $method);
    }

    /**
     * The class a source file declares, and its spec.
     */
    private function declaredIn(string $sourceFile): RefactorTarget
    {
        if (!$this->filesystem->exists($sourceFile)) {
            throw new UnresolvedTargetException(sprintf('Source file not found: %s.', $this->relative($sourceFile)));
        }

        $fqcn = Members::in($this->filesystem->read($sourceFile))->classes()[0] ?? null;

        if ($fqcn === null) {
            throw new UnresolvedTargetException(sprintf('%s declares no class to refactor.', $this->relative($sourceFile)));
        }

        return new RefactorTarget($fqcn, $sourceFile, $this->specFileFor($fqcn));
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') || preg_match('~^[A-Za-z]:/~', $path) === 1 ? $path : $this->root . '/' . $path;
    }

    private function relative(string $file): string
    {
        return str_starts_with($file, $this->root . '/') ? substr($file, strlen($this->root) + 1) : $file;
    }

    /**
     * The class a short name stands for: the one by that name modified last
     * in any directory the project's classes are in.
     */
    private function classNamed(string $shortName): string
    {
        $newest = (new RecencyScanner($this->filesystem))->mostRecentNamed($shortName . '.php', ...$this->sourceDirectories());
        $declared = $newest === null ? [] : Members::in($this->filesystem->read($newest))->classes();

        foreach ($declared as $fqcn) {
            if ($fqcn === $shortName || str_ends_with($fqcn, '\\' . $shortName)) {
                return $fqcn;
            }
        }

        throw new UnresolvedTargetException(sprintf('No class named %s under %s.', $shortName, $this->sourceRootsNamed()), 'Describe it first: ' . $this->commandLine->for('describe', $shortName));
    }

    /**
     * @return list<string>
     */
    private function sourceDirectories(): array
    {
        return array_map(fn(string $directory): string => rtrim($this->root . '/' . $directory, '/'), $this->config->sourceRoots());
    }

    private function sourceRootsNamed(): string
    {
        $roots = array_map(static fn(string $root): string => $root === '' ? '.' : $root, $this->config->sourceRoots());
        $last = array_pop($roots);

        return $roots === [] ? (string) $last : implode(', ', $roots) . ' or ' . $last;
    }

    /**
     * The class a spec file describes, read from where it sits under the spec
     * path: spec/App/Basket.spec.php describes App\Basket.
     */
    private function classDescribedBy(string $specFile, string $suffix): string
    {
        $relative = substr($specFile, strlen($this->root) + 1, -strlen($suffix));
        $specPath = $this->config->specDir() . '/';

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
        return $this->root . '/' . $this->config->specDir() . '/' . str_replace('\\', '/', $fqcn) . $this->config->getSpecSuffix();
    }
}
