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
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
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
     * The type a double stands for: what an answer's call declares, never the
     * base it is built on, and for any other double the type it was made of.
     */
    private function typeOf(object $double): string
    {
        if ($double instanceof MatchableDouble) {
            return self::declared((new ReflectionMethod($double->______PhpSpecGetDouble(), $double->______PhpSpecGetMethod()))->getReturnType());
        }

        if ($double instanceof GeneratedDouble) {
            return ltrim($double->______PhpSpecNameOfClassDoubled(), '\\');
        }

        return $double::class;
    }

    /**
     * A return type as the reader wrote it, without the null a nullable one
     * allows, and mixed where none was written.
     */
    private static function declared(?ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if (!$type instanceof ReflectionUnionType) {
            return (string) $type;
        }

        $members = array_values(array_filter($type->getTypes(), static fn(ReflectionType $member): bool => (string) $member !== 'null'));

        return count($members) === 1
            ? (string) $members[0]
            : implode('|', array_map(static fn(ReflectionType $member): string => $member instanceof ReflectionIntersectionType ? '(' . $member . ')' : (string) $member, $members));
    }
}
