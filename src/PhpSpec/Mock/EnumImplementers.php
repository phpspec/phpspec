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

namespace PhpSpec\Mock;

use Composer\Autoload\ClassLoader;
use FilesystemIterator;
use PhpSpec\OwnCode;
use PhpSpec\Source\Declarations;
use PhpSpec\Source\ProjectAutoloader;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;
use UnexpectedValueException;
use UnitEnum;

/**
 * @internal
 * The enums implementing an interface only an enum can implement: those
 * already loaded, and those declared in the project's source, the
 * interface's own namespace's included. Their declarations are read for
 * every interface extending it, at any depth, and only an enum implementing
 * one of them is loaded, to check it. Each file is read once.
 */
final class EnumImplementers
{
    /** @var array<string, Declarations> what each file read declares */
    private array $read = [];

    /**
     * @param list<string> $roots the directories and files searched for any interface
     * @param list<string> $skipped directories never searched
     */
    public function __construct(
        private readonly array $roots = [],
        private readonly array $skipped = [],
    ) {}

    /**
     * Searches what the registered autoloaders load the project's own
     * classes from, and never the directories Composer keeps libraries in.
     */
    public static function here(): self
    {
        return self::of(spl_autoload_functions(), OwnCode::here());
    }

    /**
     * Searches what the autoloaders load the project's own classes from:
     * the directories the configuration names, and Composer's, prefixed or
     * not, and its classmap files, each under the project and outside its
     * libraries.
     *
     * @param array<mixed> $autoloaders as spl_autoload_functions() lists them
     */
    public static function of(array $autoloaders, OwnCode $own): self
    {
        $roots = [];

        foreach ($autoloaders as $autoloader) {
            if ($autoloader instanceof ProjectAutoloader) {
                array_push($roots, ...$autoloader->directories());
            } elseif (is_array($autoloader) && ($autoloader[0] ?? null) instanceof ClassLoader) {
                array_push($roots, ...self::composerPaths($autoloader[0]));
            }
        }

        $roots = array_filter(array_map(self::real(...), $roots), $own->holds(...));

        return new self(array_values(array_unique($roots)), array_keys(ClassLoader::getRegisteredLoaders()));
    }

    /**
     * The first case of the first enum, by name, implementing the interface,
     * or null when no enum with a case does.
     *
     * @param class-string $interface
     */
    public function firstCaseOf(string $interface): ?UnitEnum
    {
        $enums = array_values(array_unique([...$this->loaded($interface), ...$this->declared($interface)]));
        sort($enums);

        foreach ($enums as $enum) {
            $cases = $enum::cases();

            if ($cases !== []) {
                return $cases[0];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function composerPaths(ClassLoader $composer): array
    {
        return [
            ...array_merge([], ...array_values($composer->getPrefixesPsr4())),
            ...$composer->getFallbackDirsPsr4(),
            ...array_merge([], ...array_values($composer->getPrefixes())),
            ...$composer->getFallbackDirs(),
            ...array_values($composer->getClassMap()),
        ];
    }

    /**
     * @return list<class-string<UnitEnum>>
     */
    private function loaded(string $interface): array
    {
        return array_values(array_filter(get_declared_classes(), static fn(string $class): bool => enum_exists($class, false) && is_subclass_of($class, $interface)));
    }

    /**
     * The enums the source declares implementing the interface, or one
     * extending it, each loaded to check it.
     *
     * @param class-string $interface
     * @return list<class-string<UnitEnum>>
     */
    private function declared(string $interface): array
    {
        $file = (new ReflectionClass($interface))->getFileName();
        $roots = $file === false || !is_file($file) ? $this->roots : [...$this->roots, $this->namespaceRoot($interface, $file)];
        $interfaces = [];
        $enums = [];

        foreach ($this->sourceFiles($roots) as $source) {
            $declared = $this->declarationsOf($source);
            $interfaces += $declared->interfaces();
            $enums += $declared->enums();
        }

        $extending = $this->extending($interface, $interfaces);
        $found = [];

        foreach ($enums as $enum => $implemented) {
            if (array_intersect($implemented, $extending) !== [] && $this->implements($enum, $interface)) {
                /** @var class-string<UnitEnum> $enum */
                $found[] = $enum;
            }
        }

        return $found;
    }

    /**
     * The interface and every interface extending it, at any depth: those
     * loaded, and those the source declares, lower-cased as PHP compares
     * names.
     *
     * @param array<string, list<string>> $declared interface to the interfaces it extends
     * @return list<string>
     */
    private function extending(string $interface, array $declared): array
    {
        $extending = [strtolower(ltrim($interface, '\\')) => true];

        foreach (get_declared_interfaces() as $loaded) {
            if (is_subclass_of($loaded, $interface)) {
                $extending[strtolower($loaded)] = true;
            }
        }

        do {
            $grew = false;

            foreach ($declared as $name => $parents) {
                if (!isset($extending[$name]) && array_intersect_key(array_flip($parents), $extending) !== []) {
                    $extending[$name] = true;
                    $grew = true;
                }
            }
        } while ($grew);

        return array_map('strval', array_keys($extending));
    }

    private function declarationsOf(string $file): Declarations
    {
        return $this->read[$file] ??= new Declarations((string) file_get_contents($file));
    }

    /**
     * The directory the interface's namespace starts in: up from its file
     * for each segment of its namespace a directory is named after.
     */
    private function namespaceRoot(string $interface, string $file): string
    {
        $directory = dirname($file);
        $segments = explode('\\', $interface);
        array_pop($segments);

        while ($segments !== [] && strcasecmp(basename($directory), (string) end($segments)) === 0) {
            array_pop($segments);
            $directory = dirname($directory);
        }

        return $directory;
    }

    /**
     * Each PHP file under the directories, or given itself, once, none in a
     * directory left out, and none that declares no interface or enum.
     *
     * @param list<string> $roots
     * @return list<string>
     */
    private function sourceFiles(array $roots): array
    {
        $skipped = array_map(self::real(...), $this->skipped);
        $files = [];

        foreach ($roots as $root) {
            foreach (is_file($root) ? [new SplFileInfo($root)] : $this->under($root, $skipped) as $entry) {
                $files[self::real($entry->getPathname())] = true;
            }
        }

        return array_values(array_filter(array_map('strval', array_keys($files)), function (string $file): bool {
            if (isset($this->read[$file])) {
                return true;
            }

            $source = (string) file_get_contents($file);

            return stripos($source, 'interface') !== false || stripos($source, 'enum') !== false;
        }));
    }

    /**
     * The PHP files under a directory, none in a directory left out or one
     * that cannot be read.
     *
     * @param list<string> $skipped
     * @return iterable<SplFileInfo>
     */
    private function under(string $root, array $skipped): iterable
    {
        if (!is_dir($root) || in_array(self::real($root), $skipped, true)) {
            return [];
        }

        try {
            $directory = new RecursiveDirectoryIterator(self::real($root), FilesystemIterator::SKIP_DOTS);
        } catch (UnexpectedValueException) {
            return [];
        }

        $searched = new RecursiveCallbackFilterIterator(
            $directory,
            static fn(SplFileInfo $entry): bool => $entry->isDir()
                ? !str_starts_with($entry->getFilename(), '.') && !in_array(self::real($entry->getPathname()), $skipped, true)
                : $entry->getExtension() === 'php',
        );

        /** @var iterable<SplFileInfo> */
        return new RecursiveIteratorIterator($searched, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
    }

    /**
     * A path as one spelling whichever way it was reached, links resolved.
     */
    private static function real(string $path): string
    {
        return rtrim(str_replace('\\', '/', realpath($path) ?: $path), '/');
    }

    private function implements(string $enum, string $interface): bool
    {
        try {
            return enum_exists($enum) && is_subclass_of($enum, $interface);
        } catch (Throwable) {
            return false;
        }
    }
}
