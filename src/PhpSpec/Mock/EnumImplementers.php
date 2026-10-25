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
use PhpSpec\Source\Tokens;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;
use UnitEnum;

/**
 * @internal
 * The enums implementing an interface only an enum can implement: those
 * already loaded, and those declared in the source of the interface's own
 * namespace, found by reading the files that name it and loading only an
 * enum declared there to check it.
 */
final readonly class EnumImplementers
{
    /**
     * @param list<string> $skipped directories never searched
     */
    public function __construct(private array $skipped = []) {}

    /**
     * Leaves out the directories the Composer autoloaders keep libraries in.
     */
    public static function here(): self
    {
        return new self(array_keys(ClassLoader::getRegisteredLoaders()));
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
     * @return list<class-string<UnitEnum>>
     */
    private function loaded(string $interface): array
    {
        return array_values(array_filter(get_declared_classes(), static fn(string $class): bool => enum_exists($class, false) && is_subclass_of($class, $interface)));
    }

    /**
     * The enums declared under the interface's namespace that implement it,
     * each loaded to check it.
     *
     * @param class-string $interface
     * @return list<class-string<UnitEnum>>
     */
    private function declared(string $interface): array
    {
        $file = (new ReflectionClass($interface))->getFileName();

        if ($file === false || !is_file($file)) {
            return [];
        }

        $short = substr(strrchr('\\' . $interface, '\\') ?: $interface, 1);
        $enums = [];

        foreach ($this->sourceFiles($this->namespaceRoot($interface, $file)) as $source) {
            foreach ($this->enumsNaming($source, $short) as $enum) {
                if ($this->implements($enum, $interface)) {
                    /** @var class-string<UnitEnum> $enum */
                    $enums[] = $enum;
                }
            }
        }

        return $enums;
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
     * @return iterable<string> the PHP files under the directory, none in a directory left out
     */
    private function sourceFiles(string $root): iterable
    {
        $skipped = array_map(self::real(...), $this->skipped);
        $searched = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator(self::real($root), FilesystemIterator::SKIP_DOTS),
            static fn(SplFileInfo $entry): bool => $entry->isDir()
                ? !str_starts_with($entry->getFilename(), '.') && !in_array(self::real($entry->getPathname()), $skipped, true)
                : $entry->getExtension() === 'php',
        );

        foreach (new RecursiveIteratorIterator($searched) as $entry) {
            if ($entry instanceof SplFileInfo) {
                yield $entry->getPathname();
            }
        }
    }

    /**
     * The enums a file declares with an implements clause, when the file
     * names the interface at all: in that clause, an import or a name that
     * holds it.
     *
     * @return list<string>
     */
    private function enumsNaming(string $file, string $short): array
    {
        $source = file_get_contents($file);

        if ($source === false || stripos($source, 'enum') === false || stripos($source, $short) === false) {
            return [];
        }

        $tokens = Tokens::of($source);
        $namespace = '';
        $enums = [];

        foreach ($tokens->list as $at => $token) {
            if ($token->is(T_NAMESPACE)) {
                $namespace = $this->namespaceAt($tokens, $at);
            } elseif ($token->is(T_ENUM) && $this->implementsAfter($tokens, $at)) {
                $name = $tokens->significantAfter($at);

                if ($name !== null) {
                    $enums[] = ltrim($namespace . '\\' . $tokens->list[$name]->text, '\\');
                }
            }
        }

        return $enums;
    }

    private function namespaceAt(Tokens $tokens, int $at): string
    {
        $name = $tokens->significantAfter($at);

        return $name !== null && $tokens->list[$name]->is([T_STRING, T_NAME_QUALIFIED]) ? $tokens->list[$name]->text : '';
    }

    private function implementsAfter(Tokens $tokens, int $at): bool
    {
        for ($i = $at + 1; $i < count($tokens->list) && $tokens->list[$i]->id !== 123; $i++) {
            if ($tokens->list[$i]->is(T_IMPLEMENTS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A directory as one path spells it whichever way it was reached, links
     * resolved.
     */
    private static function real(string $directory): string
    {
        return rtrim(str_replace('\\', '/', realpath($directory) ?: $directory), '/');
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
