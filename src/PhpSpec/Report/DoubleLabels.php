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

namespace PhpSpec\Report;

use PhpSpec\Mock\GeneratedDouble;
use PhpSpec\Mock\MatchableDouble;
use ReflectionClass;
use WeakMap;

/**
 * @internal
 * How a report names the doubles of the example or scenario under way: by the
 * type each stands for and the number it was given when it was made, with the
 * parameter names it was handed under. What a double answered is named by
 * where it came from, and numbered when it is first shown. A number, once
 * given, is the double's for the rest of the example.
 */
final class DoubleLabels
{
    private static ?self $book = null;

    /** @var WeakMap<object, int> */
    private WeakMap $numbers;

    /** @var WeakMap<object, list<string>> */
    private WeakMap $names;

    private int $given = 0;

    private function __construct()
    {
        $this->numbers = new WeakMap();
        $this->names = new WeakMap();
    }

    /**
     * Opens a fresh book, numbering from one, for an example or a scenario
     * about to be set up.
     */
    public static function start(): void
    {
        self::$book = new self();
    }

    /**
     * The book in use, for a run inside it to put back when it ends.
     */
    public static function save(): self
    {
        return self::book();
    }

    public static function restore(self $book): void
    {
        self::$book = $book;
    }

    /**
     * Gives a double the spec made the next number, unless it has one, and
     * the parameter name it was handed under.
     */
    public static function made(object $double, ?string $parameter = null): void
    {
        $book = self::book();
        $book->numberOf($double);
        $names = $book->names[$double] ?? [];

        if ($parameter !== null && !in_array($parameter, $names, true)) {
            $book->names[$double] = [...$names, $parameter];
        }
    }

    public static function of(object $double): string
    {
        return self::book()->label($double);
    }

    private static function book(): self
    {
        return self::$book ??= new self();
    }

    private function label(object $double): string
    {
        if ($double instanceof MatchableDouble) {
            return sprintf('%s (from %s)', $this->numbered($double), $this->origin($double));
        }

        $names = $this->names[$double] ?? [];

        return $names === [] ? $this->numbered($double) : sprintf('%s ($%s)', $this->numbered($double), implode(', $', $names));
    }

    private function numbered(object $double): string
    {
        return sprintf('%s double #%d', $this->typeOf($double), $this->numberOf($double));
    }

    private function numberOf(object $double): int
    {
        return $this->numbers[$double] ??= ++$this->given;
    }

    /**
     * The double a chain of answers starts at, and the calls down to this one.
     */
    private function origin(MatchableDouble $answer): string
    {
        $producer = $answer->______PhpSpecGetDouble();
        $call = '->' . $answer->______PhpSpecGetMethod() . '()';

        if ($producer instanceof MatchableDouble) {
            return $this->origin($producer) . $call;
        }

        return $this->numbered($producer) . $call;
    }

    /**
     * The type a double stands for: the one it was made of, or for what a
     * mock answered, the class it extends or else the interfaces it
     * implements, none of them one another names.
     */
    private function typeOf(object $double): string
    {
        if ($double instanceof GeneratedDouble) {
            return ltrim($double->______PhpSpecNameOfClassDoubled(), '\\');
        }

        $class = new ReflectionClass($double);
        $parent = $class->getParentClass();

        if ($parent !== false) {
            return $parent->getName();
        }

        $interfaces = array_filter($class->getInterfaceNames(), static fn(string $interface): bool => !str_starts_with($interface, 'PhpSpec\\Mock\\'));
        $own = array_filter($interfaces, static function (string $interface) use ($interfaces): bool {
            foreach ($interfaces as $other) {
                if (is_subclass_of($other, $interface)) {
                    return false;
                }
            }

            return true;
        });

        return implode('&', $own);
    }
}
