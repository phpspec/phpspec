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

use Closure;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Traversable;

/**
 * @internal
 * A declared type as the values it admits: a union of terms, each the
 * intersection of the single types it names. Several declarations of one
 * method meet in the type an implementation of all of them returns, and join
 * in the type it takes. A term no value can satisfy, such as int with
 * string, or two classes neither extends, is no term at all.
 */
final readonly class DeclaredType
{
    private const SCALARS = ['int', 'float', 'string', 'true', 'false', 'null', 'array', 'callable', 'void', 'never'];

    /**
     * @param list<list<string>>|null $terms null for mixed, which admits any value but none from void
     * @param list<string> $own the types of the double, which static stands for
     */
    private function __construct(
        private ?array $terms,
        private array $own,
    ) {}

    /**
     * @param list<string> $own the types of the double, which static stands for
     */
    public static function of(?ReflectionType $type, ReflectionMethod $method, array $own): self
    {
        $terms = $type === null ? null : self::termsOf($type, $method);

        if ($terms === null) {
            return new self(null, $own);
        }

        $reducing = new self([], $own);

        return new self($reducing->widest(array_values(array_filter(array_map($reducing->reduced(...), $terms), static fn(?array $term): bool => $term !== null))), $own);
    }

    /**
     * The values every one of the classes and interfaces admits.
     *
     * @param list<string> $types
     */
    public static function allOf(array $types): self
    {
        $term = (new self([], []))->reduced(array_map(self::canonical(...), $types));

        return new self($term === null ? [] : [$term], []);
    }

    /**
     * The values both types admit.
     */
    public function meet(self $other): self
    {
        if ($this->terms === null || $other->terms === null) {
            $terms = $this->terms ?? $other->terms;

            return new self($terms === [['void']] ? [] : $terms, $this->own);
        }

        $met = [];

        foreach ($this->terms as $mine) {
            foreach ($other->terms as $theirs) {
                $term = $this->reduced([...$mine, ...$theirs]);

                if ($term !== null) {
                    $met[] = $term;
                }
            }
        }

        return new self($this->widest($met), $this->own);
    }

    /**
     * The values either type admits.
     */
    public function join(self $other): self
    {
        if ($this->terms === null || $other->terms === null) {
            return new self(null, $this->own);
        }

        return new self($this->widest([...$this->terms, ...$other->terms]), $this->own);
    }

    /**
     * Whether every value this type admits the other admits too.
     */
    public function within(self $other): bool
    {
        if ($other->terms === null) {
            return $this->terms !== [['void']];
        }

        if ($this->terms === null) {
            return false;
        }

        foreach ($this->terms as $term) {
            if (!$this->termWithinAny($term, $other->terms)) {
                return false;
            }
        }

        return true;
    }

    public function admitsNoValue(): bool
    {
        return $this->terms === [];
    }

    public function isMixed(): bool
    {
        return $this->terms === null;
    }

    /**
     * The type as PHP code, each class named from the root namespace.
     */
    public function code(): string
    {
        return $this->written(static fn(string $class): string => '\\' . $class);
    }

    /**
     * The type as a reader writes it, for a message.
     */
    public function shown(): string
    {
        return $this->written(static fn(string $class): string => $class);
    }

    /**
     * @param Closure(string): string $class
     */
    private function written(Closure $class): string
    {
        if ($this->terms === null) {
            return 'mixed';
        }

        $terms = $this->terms;
        $bool = in_array(['true'], $terms, true) && in_array(['false'], $terms, true);

        if ($bool) {
            $terms = array_values(array_filter($terms, static fn(array $term): bool => $term !== ['true'] && $term !== ['false']));
        }

        $written = array_map(
            fn(array $term): string => implode('&', array_map(fn(string $atom): string => $this->isClass($atom) ? $class($atom) : $atom, $term)),
            $terms,
        );

        if ($bool) {
            $written[] = 'bool';
        }

        if (count($written) > 1) {
            $written = array_map(static fn(string $term): string => str_contains($term, '&') ? '(' . $term . ')' : $term, $written);
        }

        return implode('|', $written);
    }

    /**
     * @return list<list<string>>|null
     */
    private static function termsOf(ReflectionType $type, ReflectionMethod $method): ?array
    {
        if ($type instanceof ReflectionUnionType) {
            $terms = [];

            foreach ($type->getTypes() as $member) {
                $memberTerms = self::termsOf($member, $method);

                if ($memberTerms === null) {
                    return null;
                }

                $terms = [...$terms, ...$memberTerms];
            }

            return $terms;
        }

        if ($type instanceof ReflectionIntersectionType) {
            return [array_values(array_map(static fn(ReflectionType $member): string => self::canonical($member instanceof ReflectionNamedType ? $member->getName() : (string) $member), $type->getTypes()))];
        }

        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        $name = $type->getName();
        $terms = match (strtolower($name)) {
            'mixed' => null,
            'bool' => [['true'], ['false']],
            'iterable' => [['array'], [Traversable::class]],
            'self' => [[$method->getDeclaringClass()->getName()]],
            'parent' => [[($method->getDeclaringClass()->getParentClass() ?: $method->getDeclaringClass())->getName()]],
            'static', 'object' => [[strtolower($name)]],
            default => [[$type->isBuiltin() ? strtolower($name) : self::canonical($name)]],
        };

        if ($terms !== null && $type->allowsNull() && $terms !== [['null']]) {
            $terms[] = ['null'];
        }

        return $terms;
    }

    /**
     * A class as PHP names it, however the declaration spelled it: an alias
     * or a name in another case is the class it stands for.
     */
    private static function canonical(string $name): string
    {
        return class_exists($name) || interface_exists($name) || enum_exists($name) ? (new ReflectionClass($name))->getName() : $name;
    }

    /**
     * The terms no other term admits more of, each once.
     *
     * @param list<list<string>> $terms
     * @return list<list<string>>
     */
    private function widest(array $terms): array
    {
        $widest = [];

        foreach ($terms as $position => $term) {
            $others = $terms;
            unset($others[$position]);
            $wider = array_filter($others, fn(array $other, int $at): bool => $this->termWithin($term, $other) && (!$this->termWithin($other, $term) || $at < $position), ARRAY_FILTER_USE_BOTH);

            if ($wider === []) {
                $widest[] = $term;
            }
        }

        return $widest;
    }

    /**
     * One term as the fewest single types it needs, or null when no value
     * can satisfy every type it names.
     *
     * @param list<string> $atoms
     * @return list<string>|null
     */
    private function reduced(array $atoms): ?array
    {
        $atoms = array_values(array_intersect_key($atoms, array_unique(array_map(strtolower(...), $atoms))));

        if (in_array('never', $atoms, true)) {
            return ['never'];
        }

        $scalars = array_values(array_intersect($atoms, self::SCALARS));
        $objects = array_values(array_diff($atoms, self::SCALARS));

        if ($scalars !== []) {
            return count($scalars) === 1 && $objects === [] ? $scalars : null;
        }

        $classes = array_values(array_filter($objects, fn(string $atom): bool => $this->isClass($atom)));

        if (in_array('static', $objects, true)) {
            foreach ($classes as $class) {
                if (!$this->atomWithin('static', $class)) {
                    return null;
                }
            }

            return ['static'];
        }

        if ($classes === []) {
            return ['object'];
        }

        $narrowest = array_values(array_filter($classes, fn(string $class): bool => array_filter($classes, static fn(string $other): bool => $other !== $class && is_a($other, $class, true)) === []));

        return $this->shareAnInstance($narrowest) ? $narrowest : null;
    }

    /**
     * Whether one object can be of every one of the classes and interfaces,
     * none of which extends another: a class at most, a final one only when
     * it already implements the rest, and none that what one class can extend
     * keeps from being every one of them. Whether PhpSpec can make a double of
     * them is another question.
     *
     * @param list<string> $types
     */
    private function shareAnInstance(array $types): bool
    {
        $classes = array_values(array_filter($types, static fn(string $type): bool => class_exists($type) || enum_exists($type)));

        if (count($classes) > 1) {
            return false;
        }

        $class = $classes === [] ? null : new ReflectionClass($classes[0]);

        if ($class !== null && ($class->isFinal() || $class->isEnum())) {
            foreach ($types as $type) {
                if (!is_a($class->getName(), $type, true)) {
                    return false;
                }
            }

            return true;
        }

        $interfaces = array_map(static fn(string $type): ReflectionClass => new ReflectionClass($type), array_values(array_filter($types, interface_exists(...))));

        return $interfaces === [] || (new NativeBase($interfaces, static fn(string $interface): ?string => null, $class))->conflict() === null;
    }

    /**
     * @param list<string> $term
     * @param list<list<string>> $terms
     */
    private function termWithinAny(array $term, array $terms): bool
    {
        foreach ($terms as $other) {
            if ($this->termWithin($term, $other)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every value the term admits the other admits too: each single
     * type the other names is one this term names, or wider than one.
     *
     * @param list<string> $term
     * @param list<string> $other
     */
    private function termWithin(array $term, array $other): bool
    {
        foreach ($other as $wanted) {
            if (array_filter($term, fn(string $atom): bool => $this->atomWithin($atom, $wanted)) === []) {
                return false;
            }
        }

        return true;
    }

    private function atomWithin(string $atom, string $wanted): bool
    {
        if (strcasecmp($atom, $wanted) === 0 || $atom === 'never') {
            return true;
        }

        if ($wanted === 'object') {
            return $atom === 'static' || $this->isClass($atom);
        }

        if ($atom === 'static') {
            return $this->isClass($wanted) && array_filter($this->own, static fn(string $own): bool => is_a($own, $wanted, true)) !== [];
        }

        return $this->isClass($atom) && $this->isClass($wanted) && is_a($atom, $wanted, true);
    }

    private function isClass(string $atom): bool
    {
        return !in_array($atom, [...self::SCALARS, 'object', 'static'], true);
    }
}
