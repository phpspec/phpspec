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
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Iterator;
use IteratorAggregate;
use LogicException;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;
use Traversable;

/**
 * @internal
 * What a class must be to implement an interface built on a built-in one PHP
 * lets no plain class implement: an IteratorAggregate for a Traversable, an
 * Exception for a Throwable, a DateTimeImmutable for a DateTimeInterface.
 * A class the double extends instead keeps what it already is: an Error or a
 * DateTime, or a Traversable, and the serialization methods it has. A
 * Traversable interface makes a subclass of it an IteratorAggregate where its
 * getIterator() allows, else an Iterator. A class that cannot be what the
 * interfaces need conflicts with them, as no subclass of it can be. Only an enum can implement
 * a UnitEnum, so no double can be one. Any other built-in interface is asked
 * about, since an extension may refuse a plain class too.
 */
final readonly class NativeBase
{
    /** The built-in interfaces it knows how a class can be */
    public const HANDLED = ['Traversable', 'Iterator', 'IteratorAggregate', 'Throwable', 'UnitEnum', 'BackedEnum', 'DateTimeInterface', 'Serializable'];

    private ?string $refusal;

    private ?string $conflict;

    private ?string $unending;

    /** @var class-string|null */
    private ?string $extends;

    /** @var list<class-string> */
    private array $implements;

    private string $code;

    /**
     * @param list<ReflectionClass<object>> $interfaces the interfaces a double must be, more than one for an intersection
     * @param Closure(class-string): ?string $refusedBy why PHP refuses a plain class implementing a built-in interface, or null
     * @param ReflectionClass<object>|null $class the class the double extends, when its types name one
     * @param list<string> $assumed the combinations of types being decided to share an instance, on the way here: deciding one again takes it to share one, once nothing else rules it out
     * @param bool $emptyIterator whether the double must be an Iterator its defaults end at once: what a dummy aggregate's getIterator() answers
     */
    public function __construct(array $interfaces, Closure $refusedBy, ?ReflectionClass $class = null, array $assumed = [], bool $emptyIterator = false)
    {
        $all = [];
        foreach ($interfaces as $interface) {
            $all = [...$all, $interface->getName(), ...$interface->getInterfaceNames()];
        }
        $all = array_values(array_unique($all));
        $declares = static fn(string $method): bool => ($class?->hasMethod($method) ?? false) || array_filter($interfaces, static fn(ReflectionClass $interface): bool => $interface->hasMethod($method)) !== [];
        $throwable = in_array('Throwable', $all, true);
        $dated = in_array(DateTimeInterface::class, $all, true);
        $enumOnly = in_array('UnitEnum', $all, true);
        $names = array_map(static fn(ReflectionClass $interface): string => $interface->getName(), $interfaces);
        $deciding = $this->combinationOf($class, $names);
        $contradiction = match (true) {
            $class !== null && $throwable && !$class->implementsInterface(Throwable::class) => sprintf('No class can extend both %s and Exception or Error.', $class->getName()),
            $class !== null && $dated && !$class->implementsInterface(DateTimeInterface::class) => sprintf('No class can extend both %s and DateTime or DateTimeImmutable.', $class->getName()),
            $class !== null && $enumOnly && !$class->isEnum() => 'Only an enum can implement UnitEnum.',
            $class === null && $throwable && $dated => 'No class can extend both Exception and DateTimeImmutable.',
            $class !== null => $this->staticClash($class, $interfaces),
            default => null,
        };
        $traversed = $contradiction === null && in_array('Traversable', $all, true) && !in_array('Iterator', $all, true) && !in_array(IteratorAggregate::class, $all, true)
            && !($class?->implementsInterface(Traversable::class) ?? false);
        [$iterator, $unimplementable] = $traversed && !$emptyIterator ? $this->iteratorOver($class, $names, [...$assumed, $deciding], in_array($deciding, $assumed, true)) : [null, null];
        [$iterator, $noEmptyIterator] = $emptyIterator && $contradiction === null ? $this->emptyIteratorOver($class, $all, $names, $assumed) : [$iterator, null];

        $this->extends = $class?->getName() ?? ($throwable ? Exception::class : ($dated ? DateTimeImmutable::class : null));
        $this->implements = $iterator === null ? [] : [$iterator];
        $serializable = in_array('Serializable', $all, true);
        $this->code = ($serializable && !$declares('__serialize') ? 'public function __serialize(): array { return []; } ' : '')
            . ($serializable && !$declares('__unserialize') ? 'public function __unserialize(array $data): void {}' : '');
        $this->conflict = $contradiction ?? $unimplementable;
        $iterates = in_array('Iterator', $all, true) || $iterator === Iterator::class || ($class?->implementsInterface(Iterator::class) ?? false);
        $this->unending = $noEmptyIterator ?? ($this->conflict === null && $iterates ? $this->unending($class, $interfaces, $iterator === Iterator::class || $emptyIterator, [...($class === null ? [] : [$class->getName()]), ...$names], $assumed) : null);
        $this->refusal = $enumOnly ? 'Only an enum can implement UnitEnum.' : $this->refusedAmong($all, $refusedBy);
    }

    /**
     * Why no class at all can be the interfaces, and the class a double
     * extends, when none can: what one class can extend conflicts with what
     * another requires. A refusal beyond it is PhpSpec's alone, which a value
     * of those types could still pass.
     */
    public function conflict(): ?string
    {
        return $this->conflict;
    }

    /**
     * Why no double can be this interface, or null when one can. One that
     * stands for a call, every call on it refused, never iterates, so whether
     * its iteration could end is none of its concern.
     */
    public function refusal(bool $iterated = true): ?string
    {
        return $this->conflict ?? ($iterated ? $this->unending : null) ?? $this->refusal;
    }

    /**
     * The class a double must extend, the one its types name or the base PHP
     * requires, or null when none.
     */
    public function extends(): ?string
    {
        return $this->extends === null ? null : '\\' . $this->extends;
    }

    /**
     * The interfaces a double must implement besides this one.
     *
     * @return list<string>
     */
    public function implements(): array
    {
        return array_map(static fn(string $interface): string => '\\' . $interface, $this->implements);
    }

    /**
     * Those interfaces, whose methods a double defines like its own.
     *
     * @return list<ReflectionClass<object>>
     */
    public function interfaces(): array
    {
        return array_map(static fn(string $interface): ReflectionClass => new ReflectionClass($interface), $this->implements);
    }

    /**
     * Code a double needs on top of its methods.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * How the class a double extends declares the method, which a double
     * leaves as it is where that class declares it final; null when it
     * declares no such method a subclass inherits.
     */
    public function declaration(string $method): ?ReflectionMethod
    {
        if ($this->extends === null || !method_exists($this->extends, $method)) {
            return null;
        }

        $declaration = new ReflectionMethod($this->extends, $method);

        return !$declaration->isPrivate() && !$declaration->isStatic() ? $declaration : null;
    }

    /**
     * The iterator a subclass of the class implements to be Traversable: an
     * IteratorAggregate where its getIterator() allows one, so a stub of
     * getIterator() rules foreach, else an Iterator where its methods allow
     * one; and why none can be, when neither fits. Deciding the same types
     * again on the way, through a getIterator() returning the class itself,
     * takes one to fit.
     *
     * @param ReflectionClass<object>|null $class
     * @param list<string> $interfaces
     * @param list<string> $assumed
     * @return array{class-string|null, string|null}
     */
    private function iteratorOver(?ReflectionClass $class, array $interfaces, array $assumed, bool $again): array
    {
        if ($class === null || $again) {
            return [IteratorAggregate::class, null];
        }

        $own = [$class->getName(), ...$interfaces];

        if ($this->unfit($class, IteratorAggregate::class, $own, $assumed) === null) {
            return [IteratorAggregate::class, null];
        }

        if ($this->unfit($class, Iterator::class, $own, $assumed) === null) {
            return [Iterator::class, null];
        }

        return [null, sprintf('No class extending %s can implement Iterator or IteratorAggregate.', $class->getName())];
    }

    /**
     * The Iterator a double must be to answer as empty, with why it cannot
     * be one: its class keeps the Iterator's methods from it. An aggregate
     * stays one, PHP iterating what it aggregates in turn, where following its
     * getIterator() reaches an Iterator without coming back to an aggregate on
     * the way.
     *
     * @param ReflectionClass<object>|null $class
     * @param list<string> $all
     * @param list<string> $interfaces
     * @param list<string> $assumed
     * @return array{class-string|null, string|null}
     */
    private function emptyIteratorOver(?ReflectionClass $class, array $all, array $interfaces, array $assumed): array
    {
        $aggregate = array_values(array_filter([$class?->getName(), ...$interfaces], static fn(?string $type): bool => $type !== null && is_a($type, IteratorAggregate::class, true)));

        if ($aggregate !== []) {
            return [null, $this->endlessFrom($aggregate[0], [strtolower($aggregate[0])])];
        }

        if (in_array('Iterator', $all, true) || ($class?->implementsInterface(Iterator::class) ?? false)) {
            return [null, null];
        }

        $unfit = $class === null ? null : $this->unfit($class, Iterator::class, [$class->getName(), ...$interfaces], $assumed);

        return $unfit === null ? [Iterator::class, null] : [null, $unfit];
    }

    /**
     * Why iterating the aggregate as a dummy could not end: every type its
     * getIterator() may return comes back to an aggregate on the way, or can
     * be no empty iterator; null when one type it may return ends.
     *
     * @param class-string $aggregate
     * @param list<string> $path the aggregates followed so far, lower-cased
     */
    private function endlessFrom(string $aggregate, array $path): ?string
    {
        $getIterator = new ReflectionMethod($aggregate, 'getIterator');
        $returns = $getIterator->getReturnType() ?? $getIterator->getTentativeReturnType();
        $members = $returns instanceof ReflectionUnionType ? $returns->getTypes() : ($returns === null ? [] : [$returns]);
        $first = null;

        foreach ($members as $member) {
            $endless = $this->endlessAt($member, $getIterator, $path);

            if ($endless === null) {
                return null;
            }

            $first ??= $endless;
        }

        return $first;
    }

    /**
     * Why a type a getIterator() returns could not answer as an empty
     * iterator, or null when it can: a built-in type has a stand-in, an
     * aggregate must end in turn, anything else must be an empty Iterator.
     *
     * @param list<string> $path
     */
    private function endlessAt(ReflectionType $member, ReflectionMethod $getIterator, array $path): ?string
    {
        $named = $member instanceof ReflectionIntersectionType ? $member->getTypes() : [$member];
        $types = [];

        foreach ($named as $type) {
            $name = $type instanceof ReflectionNamedType ? match (strtolower($type->getName())) {
                'self', 'static' => $getIterator->class,
                'parent' => $getIterator->getDeclaringClass()->getParentClass() === false ? null : $getIterator->getDeclaringClass()->getParentClass()->getName(),
                default => $type->isBuiltin() ? null : $type->getName(),
            } : null;

            if ($name === null || !(class_exists($name) || interface_exists($name))) {
                return null;
            }

            $types[] = new ReflectionClass($name);
        }

        foreach ($types as $type) {
            if (!$type->implementsInterface(IteratorAggregate::class)) {
                continue;
            }

            if (in_array(strtolower($type->getName()), $path, true)) {
                return sprintf('%s::%s() returns %s, so its iteration could not end.', $getIterator->class, $getIterator->getName(), implode('&', array_map(static fn(ReflectionClass $named): string => $named->getName(), $types)));
            }

            return $this->endlessFrom($type->getName(), [...$path, strtolower($type->getName())]);
        }

        $class = array_values(array_filter($types, static fn(ReflectionClass $type): bool => !$type->isInterface()))[0] ?? null;

        if ($class !== null && ($class->isFinal() || $class->isReadOnly())) {
            return sprintf('%s is final, so no double of it can be made.', $class->getName());
        }

        $interfaces = array_values(array_filter($types, static fn(ReflectionClass $type): bool => $type->isInterface()));

        return (new self($interfaces, static fn(string $interface): ?string => null, $class, [], true))->refusal();
    }

    /**
     * Why no double iterating as an Iterator can end its iteration: the
     * valid() of a class it extends instead of an aggregate is final, or the
     * valid() every declaration resolves to cannot return false. An Iterator
     * the class already is keeps its own final valid().
     *
     * @param ReflectionClass<object>|null $class
     * @param list<ReflectionClass<object>> $interfaces
     * @param list<string> $own
     * @param list<string> $assumed
     */
    private function unending(?ReflectionClass $class, array $interfaces, bool $fallback, array $own, array $assumed): ?string
    {
        $classValid = $class !== null && $class->hasMethod('valid') && !$class->getMethod('valid')->isPrivate() ? $class->getMethod('valid') : null;

        if ($classValid !== null && $classValid->isFinal()) {
            return $fallback ? sprintf('%s declares valid() final, so a double of it cannot end its iteration.', $classValid->class) : null;
        }

        $declarations = array_values(array_filter([
            $classValid,
            ...array_map(static fn(ReflectionClass $interface): ?ReflectionMethod => $interface->hasMethod('valid') ? $interface->getMethod('valid') : null, $interfaces),
            $fallback ? new ReflectionMethod(Iterator::class, 'valid') : null,
        ]));

        foreach ($declarations as $declaration) {
            $returns = DeclaredType::of($declaration->getReturnType() ?? $declaration->getTentativeReturnType(), $declaration, $own, $assumed);

            if (!$returns->admits('false')) {
                return sprintf('%s declares valid() returning %s, so a double of it cannot end its iteration.', $declaration->class, $returns->shown());
            }
        }

        return null;
    }

    /**
     * Why no class extending the class can implement the interfaces: it
     * declares static, and not private, a method one of them declares on an
     * object.
     *
     * @param ReflectionClass<object> $class
     * @param list<ReflectionClass<object>> $interfaces
     */
    private function staticClash(ReflectionClass $class, array $interfaces): ?string
    {
        foreach ($interfaces as $interface) {
            foreach ($interface->getMethods() as $declaration) {
                if ($declaration->isStatic() || !$class->hasMethod($declaration->getName())) {
                    continue;
                }

                $method = $class->getMethod($declaration->getName());

                if ($method->isStatic() && !$method->isPrivate()) {
                    return sprintf('%s declares %s() static, so no class extending it can implement %s::%s().', $class->getName(), $method->getName(), $declaration->class, $declaration->getName());
                }
            }
        }

        return null;
    }

    /**
     * The class and the interfaces, as one combination to decide about.
     *
     * @param ReflectionClass<object>|null $class
     * @param list<string> $interfaces
     */
    private function combinationOf(?ReflectionClass $class, array $interfaces): string
    {
        $types = array_map(strtolower(...), [...($class === null ? [] : [$class->getName()]), ...$interfaces]);
        sort($types);

        return implode('&', $types);
    }

    /**
     * What keeps a subclass of the class from implementing the interface, or
     * null when nothing does: a method of it the class declares static, or
     * final where the interface's could not be it, or otherwise such that no
     * override satisfies both.
     *
     * @param ReflectionClass<object> $class
     * @param class-string $interface
     * @param list<string> $own
     * @param list<string> $assumed
     */
    private function unfit(ReflectionClass $class, string $interface, array $own, array $assumed): ?string
    {
        foreach ((new ReflectionClass($interface))->getMethods() as $declaration) {
            if (!$class->hasMethod($declaration->getName()) || $class->getMethod($declaration->getName())->isPrivate()) {
                continue;
            }

            $method = $class->getMethod($declaration->getName());

            if ($method->isStatic()) {
                return sprintf('%s declares %s() static, so no class extending it can implement %s::%s().', $method->class, $method->getName(), $declaration->class, $declaration->getName());
            }

            if ($method->isFinal()) {
                if (!MethodContract::satisfies($method, $declaration, $own, $assumed)) {
                    return sprintf('%s::%s() narrows a method %s declares final.', $declaration->class, $declaration->getName(), $method->class);
                }

                continue;
            }

            try {
                $noValue = MethodContract::of($class->getName(), [$method, $declaration], $own, $assumed)->noValue;
            } catch (LogicException $e) {
                return $e->getMessage();
            }

            if ($noValue !== null) {
                return $noValue;
            }
        }

        return null;
    }

    /**
     * @param list<class-string> $interfaces
     * @param Closure(class-string): ?string $refusedBy
     */
    private function refusedAmong(array $interfaces, Closure $refusedBy): ?string
    {
        foreach ($interfaces as $name) {
            if (in_array($name, self::HANDLED, true) || !(new ReflectionClass($name))->isInternal()) {
                continue;
            }

            $refused = $refusedBy($name);

            if ($refused !== null) {
                return sprintf('PHP refuses a class implementing %s: %s.', $name, rtrim($refused, '.'));
            }
        }

        return null;
    }
}
