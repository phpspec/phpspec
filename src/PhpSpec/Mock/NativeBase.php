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
use ReflectionMethod;
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
    private const HANDLED = ['Traversable', 'Iterator', 'IteratorAggregate', 'Throwable', 'UnitEnum', 'BackedEnum', 'DateTimeInterface', 'Serializable'];

    private ?string $refusal;

    private ?string $conflict;

    /** @var class-string|null */
    private ?string $extends;

    /** @var list<class-string> */
    private array $implements;

    private string $code;

    /**
     * @param list<ReflectionClass<object>> $interfaces the interfaces a double must be, more than one for an intersection
     * @param Closure(class-string): ?string $refusedBy why PHP refuses a plain class implementing a built-in interface, or null
     * @param ReflectionClass<object>|null $class the class the double extends, when its types name one
     */
    public function __construct(array $interfaces, Closure $refusedBy, ?ReflectionClass $class = null)
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
        $traversed = in_array('Traversable', $all, true) && !in_array('Iterator', $all, true) && !in_array(IteratorAggregate::class, $all, true)
            && !($class?->implementsInterface(Traversable::class) ?? false);
        [$iterator, $unimplementable, $unending] = $traversed ? $this->iteratorOver($class, [...array_map(static fn(ReflectionClass $interface): string => $interface->getName(), $interfaces)]) : [null, null, null];

        $this->extends = $class?->getName() ?? ($throwable ? Exception::class : ($dated ? DateTimeImmutable::class : null));
        $this->implements = $iterator === null ? [] : [$iterator];
        $serializable = in_array('Serializable', $all, true);
        $this->code = ($serializable && !$declares('__serialize') ? 'public function __serialize(): array { return []; } ' : '')
            . ($serializable && !$declares('__unserialize') ? 'public function __unserialize(array $data): void {}' : '');
        $this->conflict = match (true) {
            $class !== null && $throwable && !$class->implementsInterface(Throwable::class) => sprintf('No class can extend both %s and Exception or Error.', $class->getName()),
            $class !== null && $dated && !$class->implementsInterface(DateTimeInterface::class) => sprintf('No class can extend both %s and DateTime or DateTimeImmutable.', $class->getName()),
            $class !== null && $enumOnly && !$class->isEnum() => 'Only an enum can implement UnitEnum.',
            $class === null && $throwable && $dated => 'No class can extend both Exception and DateTimeImmutable.',
            default => $unimplementable,
        };
        $this->refusal = $this->conflict ?? $unending ?? ($enumOnly ? 'Only an enum can implement UnitEnum.' : $this->refusedAmong($all, $refusedBy));
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
     * Why no double can be this interface, or null when one can.
     */
    public function refusal(): ?string
    {
        return $this->refusal;
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
     * declares no such public method.
     */
    public function declaration(string $method): ?ReflectionMethod
    {
        if ($this->extends === null || !method_exists($this->extends, $method)) {
            return null;
        }

        $declaration = new ReflectionMethod($this->extends, $method);

        return $declaration->isPublic() && !$declaration->isStatic() ? $declaration : null;
    }

    /**
     * The iterator a subclass of the class implements to be Traversable: an
     * IteratorAggregate where its getIterator() allows one, so a stub of
     * getIterator() rules foreach, else an Iterator where its methods allow
     * one. Why none can be, when neither fits; and why no double of it can end
     * its iteration, when its valid() is final.
     *
     * @param ReflectionClass<object>|null $class
     * @param list<string> $interfaces
     * @return array{class-string|null, string|null, string|null}
     */
    private function iteratorOver(?ReflectionClass $class, array $interfaces): array
    {
        if ($class === null) {
            return [IteratorAggregate::class, null, null];
        }

        $own = [$class->getName(), ...$interfaces];

        if ($this->fits($class, IteratorAggregate::class, $own)) {
            return [IteratorAggregate::class, null, null];
        }

        if (!$this->fits($class, Iterator::class, $own)) {
            return [null, sprintf('No class extending %s can implement Iterator or IteratorAggregate.', $class->getName()), null];
        }

        if ($class->hasMethod('valid') && $class->getMethod('valid')->isFinal()) {
            return [Iterator::class, null, sprintf('%s declares valid() final, so a double of it cannot end its iteration.', $class->getName())];
        }

        return [Iterator::class, null, null];
    }

    /**
     * Whether a subclass of the class can implement the interface: each of its
     * methods the class declares final satisfies the interface's, and each it
     * declares otherwise can be overridden to satisfy both.
     *
     * @param ReflectionClass<object> $class
     * @param class-string $interface
     * @param list<string> $own
     */
    private function fits(ReflectionClass $class, string $interface, array $own): bool
    {
        foreach ((new ReflectionClass($interface))->getMethods() as $declaration) {
            if (!$class->hasMethod($declaration->getName()) || $class->getMethod($declaration->getName())->isPrivate()) {
                continue;
            }

            $method = $class->getMethod($declaration->getName());

            if ($method->isFinal()) {
                if (!MethodContract::satisfies($method, $declaration, $own)) {
                    return false;
                }

                continue;
            }

            try {
                if (MethodContract::of($class->getName(), [$method, $declaration], $own)->noValue !== null) {
                    return false;
                }
            } catch (LogicException) {
                return false;
            }
        }

        return true;
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
