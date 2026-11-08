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

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use SplFileInfo;

/**
 * @internal
 * The classes and functions in-process runs declared from the projects they
 * ran. They stay declared for every later run in this process, which sees
 * them in place of its own files, or where it expects none.
 */
final class InProcessSymbols
{
    /** @var array<string, array{name: string, owner: string, file: string, content: string|null}> by kind and lowercased name */
    private array $declared = [];

    private readonly string $ownRoot;

    /**
     * @param string|null $ownRoot where phpspec and its dependencies live, whose symbols every run shares; the checkout this class is in when null
     */
    public function __construct(?string $ownRoot = null)
    {
        $root = $ownRoot ?? dirname(__DIR__, 2);
        $this->ownRoot = rtrim(realpath($root) ?: $root, '/') . '/';
    }

    /**
     * Runs a run in the project, recording each class and function it
     * declares with the content its file had before the run began.
     *
     * @template T
     * @param callable(): T $run
     * @return T
     */
    public function record(string $projectDir, callable $run): mixed
    {
        $classes = $this->classes();
        $functions = get_defined_functions()['user'];
        $contents = $this->contentsOf($projectDir);

        try {
            return $run();
        } finally {
            foreach (array_diff($this->classes(), $classes) as $class) {
                $reflection = new ReflectionClass($class);
                $this->remember('class', $class, $reflection->isAnonymous() ? false : $reflection->getFileName(), $projectDir, $contents);
            }

            foreach (array_diff(get_defined_functions()['user'], $functions) as $function) {
                $this->remember('function', $function, (new ReflectionFunction($function))->getFileName(), $projectDir, $contents);
            }
        }
    }

    /**
     * Why a run in the project would see a class or function other than as
     * its own files declare it, or null when it would not: one another
     * project declared that it names, or one of its own whose file changed
     * since a run declared it.
     */
    public function conflictIn(string $projectDir, string $args): ?string
    {
        $named = null;

        foreach ($this->declared as $symbol) {
            if ($symbol['owner'] === $projectDir) {
                if ($symbol['content'] === null || $symbol['content'] !== $this->contentOf($symbol['file'])) {
                    return sprintf('%s was declared from "%s" before it changed.', $symbol['name'], $symbol['file']);
                }

                continue;
            }

            $named ??= $this->namesIn($projectDir, $args);

            if ($this->mentions($named, $symbol['name'])) {
                return sprintf('%s is already declared from "%s".', $symbol['name'], $symbol['file']);
            }
        }

        return null;
    }

    /**
     * @return list<class-string>
     */
    private function classes(): array
    {
        return [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()];
    }

    /**
     * @param array<string, string> $contents
     */
    private function remember(string $kind, string $name, string|false $file, string $projectDir, array $contents): void
    {
        if ($file === false || str_starts_with($file, $this->ownRoot)) {
            return;
        }

        $this->declared[$kind . ' ' . strtolower($name)] = [
            'name' => $name,
            'owner' => $projectDir,
            'file' => $file,
            'content' => $contents[$file] ?? null,
        ];
    }

    /**
     * @return array<string, string> each file's content hash, by its real path
     */
    private function contentsOf(string $projectDir): array
    {
        $contents = [];

        foreach ($this->filesIn($projectDir) as $file) {
            $content = $this->contentOf($file->getPathname());

            if ($content !== null) {
                $contents[$file->getRealPath() ?: $file->getPathname()] = $content;
            }
        }

        return $contents;
    }

    private function contentOf(string $file): ?string
    {
        return is_file($file) ? (md5_file($file) ?: null) : null;
    }

    /**
     * The names the project's files and its run's arguments mention, each
     * also by every tail of it, and the namespaces they declare. A path
     * names a class as well as code does, and an escaped backslash is one.
     *
     * @return array{names: array<string, true>, namespaces: array<string, true>}
     */
    private function namesIn(string $projectDir, string $args): array
    {
        $text = $args;

        foreach ($this->filesIn($projectDir) as $file) {
            $text .= "\n" . file_get_contents($file->getPathname());
        }

        $text = (string) preg_replace('/\\\\+/', '\\', strtolower(str_replace('/', '\\', $text)));
        preg_match_all('/(?<![\w$\\\\>:])\\\\?([a-z_\x80-\xff][\w\x80-\xff]*(?:\\\\[a-z_\x80-\xff][\w\x80-\xff]*)*)/', $text, $tokens);
        preg_match_all('/\bnamespace\s+([\w\x80-\xff\\\\]+)\s*[;{]/', $text, $namespaces);
        $names = [];

        foreach ($tokens[1] as $token) {
            $parts = explode('\\', $token);

            while ($parts !== []) {
                $names[implode('\\', $parts)] = true;
                array_shift($parts);
            }
        }

        return ['names' => $names, 'namespaces' => array_fill_keys($namespaces[1], true)];
    }

    /**
     * @param array{names: array<string, true>, namespaces: array<string, true>} $named
     */
    private function mentions(array $named, string $name): bool
    {
        $name = strtolower($name);
        $at = strrpos($name, '\\');

        if ($at === false) {
            return isset($named['names'][$name]);
        }

        return isset($named['names'][$name])
            || (isset($named['names'][substr($name, $at + 1)]) && isset($named['namespaces'][substr($name, 0, $at)]));
    }

    /**
     * Every file under the project; a linked directory, such as the vendor
     * one a scenario links in, is not followed.
     *
     * @return \Generator<SplFileInfo>
     */
    private function filesIn(string $projectDir): \Generator
    {
        if (!is_dir($projectDir)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectDir, FilesystemIterator::SKIP_DOTS)) as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isFile()) {
                yield $entry;
            }
        }
    }
}
