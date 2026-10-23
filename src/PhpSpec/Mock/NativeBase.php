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
use IteratorAggregate;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 * What a class must be to implement an interface built on a built-in one PHP
 * lets no plain class implement: an IteratorAggregate for a Traversable, an
 * Exception for a Throwable, a DateTimeImmutable for a DateTimeInterface.
 * Only an enum can implement a UnitEnum, so no double can be one. Any other
 * built-in interface is asked about, since an extension may refuse a plain
 * class too.
 */
final readonly class NativeBase
{
    private const HANDLED = ['Traversable', 'Iterator', 'IteratorAggregate', 'Throwable', 'UnitEnum', 'BackedEnum', 'DateTimeInterface', 'Serializable'];

    private ?string $refusal;

    /** @var class-string|null */
    private ?string $extends;

    /** @var list<string> */
    private array $implements;

    /** @var list<ReflectionMethod> */
    private array $methods;

    private string $code;

    /** @var list<string> lower-cased */
    private array $finals;

    /**
     * @param list<ReflectionClass<object>> $interfaces the interfaces a double must be, more than one for an intersection
     * @param Closure(class-string): ?string $refusedBy why PHP refuses a plain class implementing a built-in interface, or null
     */
    public function __construct(array $interfaces, Closure $refusedBy)
    {
        $all = [];
        foreach ($interfaces as $interface) {
            $all = [...$all, $interface->getName(), ...$interface->getInterfaceNames()];
        }
        $all = array_values(array_unique($all));
        $serializes = array_filter($interfaces, static fn(ReflectionClass $interface): bool => $interface->hasMethod('__serialize')) !== [];
        $throwable = in_array('Throwable', $all, true);
        $dated = in_array(DateTimeInterface::class, $all, true);
        $traversed = in_array('Traversable', $all, true) && !in_array('Iterator', $all, true) && !in_array(IteratorAggregate::class, $all, true);

        $this->extends = $throwable ? Exception::class : ($dated ? DateTimeImmutable::class : null);
        $this->implements = $traversed ? ['\\' . IteratorAggregate::class] : [];
        $this->methods = $traversed ? [new ReflectionMethod(IteratorAggregate::class, 'getIterator')] : [];
        $this->code = in_array('Serializable', $all, true) && !$serializes
            ? 'public function __serialize(): array { return []; } public function __unserialize(array $data): void {}'
            : '';
        $this->finals = $this->extends === null ? [] : $this->finalsOf($this->extends);
        $this->refusal = match (true) {
            in_array('UnitEnum', $all, true) => 'Only an enum can implement UnitEnum.',
            $throwable && $dated => 'No class can extend both Exception and DateTimeImmutable.',
            default => $this->refusedAmong($all, $refusedBy),
        };
    }

    /**
     * Why no double can be this interface, or null when one can.
     */
    public function refusal(): ?string
    {
        return $this->refusal;
    }

    /**
     * The class a double must extend, or null when none.
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
        return $this->implements;
    }

    /**
     * The methods those interfaces bring, which a double defines like its own.
     *
     * @return list<ReflectionMethod>
     */
    public function methods(): array
    {
        return $this->methods;
    }

    /**
     * Code a double needs on top of its methods.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Whether a double leaves the method as the class it extends has it,
     * since that class declares it final.
     */
    public function keeps(string $method): bool
    {
        return in_array(strtolower($method), $this->finals, true);
    }

    /**
     * @param class-string $class
     * @return list<string>
     */
    private function finalsOf(string $class): array
    {
        $finals = [];

        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            if ($method->isFinal()) {
                $finals[] = strtolower($method->getName());
            }
        }

        return $finals;
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
