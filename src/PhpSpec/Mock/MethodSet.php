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

use LogicException;
use ReflectionClass;
use ReflectionMethod;

/**
 * @internal
 * The methods a double of some types defines, each resolved from every
 * declaration of it: the types' own, those of the interfaces the double's
 * base brings, and those of the class it extends, whose final methods the
 * double keeps.
 * When PHP lets no class implement them all, there are none, and the reason.
 */
final readonly class MethodSet
{
    /**
     * @param list<MethodContract> $contracts
     * @param string|null $refusal why no class can implement every declaration, when none can
     */
    private function __construct(
        public array $contracts,
        public ?string $refusal,
    ) {}

    /**
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     */
    public static function of(array $types, ?NativeBase $base): self
    {
        $own = array_map(static fn(ReflectionClass $type): string => $type->getName(), $types);
        [$declared, $owners] = self::declarationsOf($types, $base, $own);
        $contracts = [];

        foreach ($declared as $key => $declarations) {
            $based = $base?->declaration($key);

            if ($based !== null && $based->isFinal()) {
                foreach ($declarations as $declaration) {
                    if (!MethodContract::satisfies($based, $declaration, $own)) {
                        return new self([], sprintf('%s::%s() narrows a method %s declares final.', $declaration->class, $declaration->getName(), $based->class));
                    }
                }

                continue;
            }

            if ($based !== null && array_filter($declarations, static fn(ReflectionMethod $declaration): bool => $declaration->class === $based->class) === []) {
                $declarations[] = $based;
            }

            try {
                $contracts[] = MethodContract::of($owners[$key], $declarations, $own);
            } catch (LogicException $e) {
                return new self([], $e->getMessage());
            }
        }

        return new self($contracts, null);
    }

    /**
     * Each method's declarations, by its lower-cased name, and the doubled
     * type that owns it.
     *
     * @param non-empty-list<ReflectionClass<object>> $types
     * @param non-empty-list<string> $own
     * @return array{array<string, non-empty-list<ReflectionMethod>>, array<string, string>}
     */
    private static function declarationsOf(array $types, ?NativeBase $base, array $own): array
    {
        $last = $own[array_key_last($own)];
        $declaring = array_map(static fn(ReflectionClass $type): array => [$type->getName(), $type], $types);

        foreach ($base?->interfaces() ?? [] as $interface) {
            $declaring[] = [$last, $interface];
        }

        $declared = [];
        $owners = [];

        foreach ($declaring as [$owner, $type]) {
            foreach ($type->getMethods() as $method) {
                // constructors, final, static and private methods, and internal magic methods stay as declared
                if ($method->getName() === '__construct' || $method->isFinal() || $method->isStatic() || $method->isPrivate() || ($method->isInternal() && str_starts_with($method->getName(), '__'))) {
                    continue;
                }

                $key = strtolower($method->getName());
                $declared[$key][$method->class] = $method;
                $owners[$key] ??= $owner;
            }
        }

        return [array_map(array_values(...), $declared), $owners];
    }
}
