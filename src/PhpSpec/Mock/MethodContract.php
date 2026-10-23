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
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

/**
 * @internal
 * A double's method, resolved from every declaration it implements: it takes
 * every call each declaration takes, a parameter by reference where every
 * declaration does, and returns a value every declared return type admits.
 * Where no value can be, it is declared never, and says why when called.
 */
final readonly class MethodContract
{
    /**
     * @param string $owner the doubled type that declares it, named in messages
     * @param list<ContractParameter> $parameters
     * @param int $required how many arguments a call must give
     * @param ReflectionType|null $returnType what it returns, null when undeclared
     * @param string $returnCode its return type as code, with the colon, empty when undeclared
     * @param string|null $noValue why no value can be returned, when none can
     */
    private function __construct(
        public string $owner,
        public string $name,
        public array $parameters,
        public int $required,
        public ?ReflectionType $returnType,
        public string $returnCode,
        public bool $byReference,
        public ?string $noValue,
    ) {}

    /**
     * @param non-empty-list<ReflectionMethod> $declarations every declaration the method implements, the owner's first
     * @param list<string> $own the types of the double, which static stands for
     * @throws LogicException when two declarations take a parameter one by reference and one by value, which no class can implement
     */
    public static function of(string $owner, array $declarations, array $own): self
    {
        if (count($declarations) === 1) {
            return self::declaredBy($owner, $declarations[0], $own);
        }

        $first = $declarations[0];
        [$returnType, $noValue] = self::returning($declarations, $own);
        $code = $returnType === null ? '' : ($noValue === null ? $returnType->code() : 'never');

        return new self(
            $owner,
            $first->getName(),
            self::merged($declarations, $own),
            min(array_map(static fn(ReflectionMethod $declaration): int => $declaration->getNumberOfRequiredParameters(), $declarations)),
            $code === '' ? null : self::reflected($code),
            $code === '' ? '' : ': ' . $code,
            array_filter($declarations, static fn(ReflectionMethod $declaration): bool => $declaration->returnsReference()) !== [],
            $noValue,
        );
    }

    /**
     * Whether every call each declaration takes, this method takes too, and
     * what it returns each declaration admits: the test a method a base class
     * declares final must pass, since a double cannot replace it.
     *
     * @param list<string> $own the types of the double, which static stands for
     */
    public static function satisfies(ReflectionMethod $method, ReflectionMethod $declaration, array $own): bool
    {
        if ($declaration->returnsReference() && !$method->returnsReference()) {
            return false;
        }

        $returns = self::returnTypeOf($declaration);

        if ($returns !== null && !DeclaredType::of(self::returnTypeOf($method), $method, $own)->within(DeclaredType::of($returns, $declaration, $own))) {
            return false;
        }

        if ($method->getNumberOfRequiredParameters() > $declaration->getNumberOfRequiredParameters()) {
            return false;
        }

        foreach ($declaration->getParameters() as $wanted) {
            $taking = self::coveringAt($method, $wanted->getPosition());

            if ($taking === null || $taking->isPassedByReference() !== $wanted->isPassedByReference() || ($wanted->isVariadic() && !$taking->isVariadic())) {
                return false;
            }

            if (!DeclaredType::of($wanted->getType(), $declaration, $own)->within(DeclaredType::of($taking->getType(), $method, $own))) {
                return false;
            }
        }

        return true;
    }

    /**
     * How many arguments a call can give at most, null when any number.
     */
    public function takes(): ?int
    {
        $last = $this->parameters[array_key_last($this->parameters) ?? 0] ?? null;

        return $last !== null && $last->variadic ? null : count($this->parameters);
    }

    /**
     * The code for a method's parameters, and for the defaults its signature
     * cannot hold, built when a call leaves them out. Every parameter takes
     * mixed, so an ArgumentMatcher can be handed to it, and has a default, so
     * the spec can write the call with fewer arguments.
     *
     * @return array{0: string, 1: string}
     */
    public function parametersCode(): array
    {
        $parameters = [];
        $defaults = '';

        foreach ($this->parameters as $parameter) {
            $type = 'mixed ' . ($parameter->byReference ? '&' : '');

            if ($parameter->variadic) {
                $parameters[] = $type . '...$' . $parameter->name;
            } elseif ($parameter->optional && $parameter->hasDefault() && self::writable($parameter->default())) {
                $parameters[] = $type . '$' . $parameter->name . ' = ' . var_export($parameter->default(), true);
            } else {
                $parameters[] = $type . '$' . $parameter->name . ($parameter->hasDefault() ? ' = \\PhpSpec\\Mock\\LeftOut::Argument' : ' = null');
                $defaults .= self::defaultCode($parameter);
            }
        }

        return [implode(', ', $parameters), $defaults];
    }

    /**
     * @param list<string> $own
     */
    private static function declaredBy(string $owner, ReflectionMethod $method, array $own): self
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            $parameters[] = new ContractParameter(
                $parameter,
                $parameter->getName(),
                $parameter->getPosition(),
                $parameter->isPassedByReference(),
                $parameter->isVariadic(),
                $parameter->isOptional(),
                $type === null ? '' : (string) $type,
                $type === null || ($type instanceof ReflectionNamedType && $type->getName() === 'mixed') ? null : self::typeIn($type, $method),
            );
        }

        $returnType = self::returnTypeOf($method);
        $returnCode = $returnType === null ? '' : ': ' . self::typeIn($returnType, $method);

        if ($returnType !== null && self::namesAnIntersection($returnType)) {
            $declared = DeclaredType::of($returnType, $method, $own);

            if (!$declared->admitsNoValue()) {
                $returnCode = ': ' . $declared->code();
                $returnType = self::reflected($declared->code());
            }
        }

        return new self(
            $owner,
            $method->getName(),
            $parameters,
            $method->getNumberOfRequiredParameters(),
            $returnType,
            $returnCode,
            $method->returnsReference(),
            null,
        );
    }

    /**
     * The default a parameter builds when the call leaves it out, since it
     * cannot be written into the double's signature: an object afresh on every
     * call, as PHP builds it, and a closure once, so a call that leaves it out
     * matches a declaration that left it out too.
     */
    private static function defaultCode(ContractParameter $parameter): string
    {
        if (!$parameter->hasDefault()) {
            return '';
        }

        return sprintf(
            'if ($%1$s === \\PhpSpec\\Mock\\LeftOut::Argument) { static $__closure_%1$s = null; $%1$s = (new \\ReflectionParameter([%2$s, %3$s], %4$s))->getDefaultValue(); if ($%1$s instanceof \\Closure) { $%1$s = $__closure_%1$s ??= $%1$s; } }',
            $parameter->name,
            var_export($parameter->source->getDeclaringClass()?->getName(), true),
            var_export($parameter->source->getDeclaringFunction()->getName(), true),
            var_export($parameter->source->getName(), true),
        );
    }

    /**
     * Whether var_export() writes the value as code a default may hold: no
     * object other than an enum case, which only runs (new, a closure).
     */
    private static function writable(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!self::writable($item)) {
                    return false;
                }
            }

            return true;
        }

        return !is_object($value) || $value instanceof UnitEnum;
    }

    /**
     * The parameters every declaration requires, as many as the longest
     * declares, and the variadic any declares after them.
     *
     * @param non-empty-list<ReflectionMethod> $declarations
     * @param list<string> $own
     * @return list<ContractParameter>
     * @throws LogicException when the declarations disagree on taking one by reference
     */
    private static function merged(array $declarations, array $own): array
    {
        $positional = max(array_map(static fn(ReflectionMethod $declaration): int => count(self::positionalOf($declaration)), $declarations));
        $required = min(array_map(static fn(ReflectionMethod $declaration): int => $declaration->getNumberOfRequiredParameters(), $declarations));
        $parameters = [];
        $names = [];

        for ($position = 0; $position < $positional; $position++) {
            $covering = self::covering($declarations, $position);
            $source = self::sourceAt($declarations, $position);
            $name = self::uniqueName($source->getName(), $names);
            $names[] = $name;
            [$declared, $takes] = self::typesOf($covering, $own);
            $parameters[] = new ContractParameter($source, $name, $position, self::byReference($covering), false, $position >= $required, $declared, $takes);
        }

        $covering = [];

        foreach ($declarations as $declaration) {
            $variadic = self::variadicOf($declaration);

            if ($variadic !== null) {
                $covering[] = [$declaration, $variadic];
            }
        }

        if ($covering !== []) {
            [$declared, $takes] = self::typesOf($covering, $own);
            $parameters[] = new ContractParameter($covering[0][1], self::uniqueName($covering[0][1]->getName(), $names), $positional, self::byReference($covering), true, true, $declared, $takes);
        }

        return $parameters;
    }

    /**
     * Each declaration's parameter at the position: the one it declares
     * there, or its variadic, which takes every position from its own on.
     *
     * @param list<ReflectionMethod> $declarations
     * @return list<array{ReflectionMethod, ReflectionParameter}>
     */
    private static function covering(array $declarations, int $position): array
    {
        $covering = [];

        foreach ($declarations as $declaration) {
            $parameter = self::coveringAt($declaration, $position);

            if ($parameter !== null) {
                $covering[] = [$declaration, $parameter];
            }
        }

        return $covering;
    }

    private static function coveringAt(ReflectionMethod $method, int $position): ?ReflectionParameter
    {
        $positional = self::positionalOf($method);

        return $positional[$position] ?? self::variadicOf($method);
    }

    /**
     * The parameter a merged position takes its name and default from: the
     * first declared there with a default, else the first declared there.
     *
     * @param list<ReflectionMethod> $declarations
     */
    private static function sourceAt(array $declarations, int $position): ReflectionParameter
    {
        $declared = [];

        foreach ($declarations as $declaration) {
            $parameter = self::positionalOf($declaration)[$position] ?? null;

            if ($parameter !== null) {
                $declared[] = $parameter;
            }
        }

        foreach ($declared as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter;
            }
        }

        return $declared[0];
    }

    /**
     * @param list<array{ReflectionMethod, ReflectionParameter}> $covering
     * @throws LogicException
     */
    private static function byReference(array $covering): bool
    {
        [$firstMethod, $first] = $covering[0];

        foreach ($covering as [$method, $parameter]) {
            if ($parameter->isPassedByReference() !== $first->isPassedByReference()) {
                throw new LogicException(sprintf(
                    '%s::%s() takes $%s %s and %s::%s() takes $%s %s.',
                    $firstMethod->class,
                    $firstMethod->getName(),
                    $first->getName(),
                    $first->isPassedByReference() ? 'by reference' : 'by value',
                    $method->class,
                    $method->getName(),
                    $parameter->getName(),
                    $parameter->isPassedByReference() ? 'by reference' : 'by value',
                ));
            }
        }

        return $first->isPassedByReference();
    }

    /**
     * The type the declarations write for a position, joined, for a message,
     * and the type an argument there is held to, null when any value goes.
     *
     * @param list<array{ReflectionMethod, ReflectionParameter}> $covering
     * @param list<string> $own
     * @return array{string, string|null}
     */
    private static function typesOf(array $covering, array $own): array
    {
        $joined = null;

        foreach ($covering as [$method, $parameter]) {
            $type = $parameter->getType();

            if ($type === null) {
                return ['', null];
            }

            $declared = DeclaredType::of($type, $method, $own);
            $joined = $joined === null ? $declared : $joined->join($declared);
        }

        if ($joined === null || $joined->isMixed()) {
            return [$joined?->shown() ?? '', null];
        }

        return [$joined->shown(), $joined->code()];
    }

    /**
     * What every declared return type admits, and why no value can be
     * returned when none is.
     *
     * @param list<ReflectionMethod> $declarations
     * @param list<string> $own
     * @return array{DeclaredType|null, string|null}
     */
    private static function returning(array $declarations, array $own): array
    {
        $met = null;
        $declaring = [];

        foreach ($declarations as $declaration) {
            $type = self::returnTypeOf($declaration);

            if ($type === null) {
                continue;
            }

            $declared = DeclaredType::of($type, $declaration, $own);
            $met = $met === null ? $declared : $met->meet($declared);
            $declaring[] = sprintf('%s::%s() returns %s', $declaration->class, $declaration->getName(), $declared->shown());
        }

        if ($met === null || !$met->admitsNoValue()) {
            return [$met, null];
        }

        $last = array_pop($declaring);

        return [$met, sprintf('%s and %s, so no value can be returned for %s.', implode(', ', $declaring), $last, count($declaring) === 1 ? 'both' : 'all of them')];
    }

    /**
     * Whether the type names an intersection, which names a class however it
     * is spelled, and as the fewest types it needs.
     */
    private static function namesAnIntersection(ReflectionType $type): bool
    {
        return $type instanceof ReflectionIntersectionType
            || ($type instanceof ReflectionUnionType && array_filter($type->getTypes(), static fn(ReflectionType $member): bool => $member instanceof ReflectionIntersectionType) !== []);
    }

    private static function reflected(string $code): ?ReflectionType
    {
        $probe = eval(sprintf('return new class { public function probe(): %s { throw new \Error(); } };', $code));

        return (new ReflectionMethod($probe, 'probe'))->getReturnType();
    }

    private static function returnTypeOf(ReflectionMethod $method): ?ReflectionType
    {
        // Tentative return types (e.g. JsonSerializable::jsonSerialize(): mixed) are not
        // exposed via getReturnType(); declaring them explicitly avoids the PHP deprecation
        // about incompatible/missing return types on internal interfaces.
        return $method->getReturnType() ?? $method->getTentativeReturnType();
    }

    /**
     * @return list<ReflectionParameter>
     */
    private static function positionalOf(ReflectionMethod $method): array
    {
        return array_values(array_filter($method->getParameters(), static fn(ReflectionParameter $parameter): bool => !$parameter->isVariadic()));
    }

    private static function variadicOf(ReflectionMethod $method): ?ReflectionParameter
    {
        $parameters = $method->getParameters();
        $last = end($parameters);

        return $last !== false && $last->isVariadic() ? $last : null;
    }

    /**
     * @param list<string> $taken
     */
    private static function uniqueName(string $name, array $taken): string
    {
        $unique = $name;

        for ($number = 2; in_array($unique, $taken, true); $number++) {
            $unique = $name . $number;
        }

        return $unique;
    }

    /**
     * A type as code outside the class that declares it: self and parent
     * named for the classes they stand for.
     */
    private static function typeIn(ReflectionType $type, ReflectionMethod $method): string
    {
        $declaring = $method->getDeclaringClass();
        $parent = $declaring->getParentClass();

        return (string) preg_replace_callback(
            '/(?<![\\\\\w])(self|parent)(?![\\\\\w])/',
            static fn(array $word): string => '\\' . ($word[1] === 'self' || $parent === false ? $declaring->getName() : $parent->getName()),
            self::formatTypeSyntax($type),
        );
    }

    /**
     * Formats a ReflectionType into valid PHP type syntax for code generation.
     * Handles named, union, and intersection types with proper namespace prefixing.
     *
     * @param ?ReflectionType $type the reflection type to format
     * @return string the PHP type syntax (e.g. "?\\Foo\\Bar", "string|int")
     */
    private static function formatTypeSyntax(?ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            if (!$type->isBuiltin() && $name !== 'self' && $name !== 'static' && $name !== 'parent') {
                $name = '\\' . $name;
            }
            return $type->allowsNull() && $name !== 'mixed' && $name !== 'null' ? '?' . $name : $name;
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $t) {
                $formatted = self::formatTypeSyntax($t);
                // Wrap intersection members in parentheses for DNF types (e.g. (A&B)|C)
                if ($t instanceof ReflectionIntersectionType) {
                    $formatted = '(' . $formatted . ')';
                }
                $parts[] = $formatted;
            }
            return implode('|', $parts);
        }

        if ($type instanceof ReflectionIntersectionType) {
            $parts = [];
            foreach ($type->getTypes() as $t) {
                if (!$t instanceof ReflectionNamedType) {
                    continue;
                }
                $name = $t->getName();
                if (!$t->isBuiltin()) {
                    $name = '\\' . $name;
                }
                $parts[] = $name;
            }
            return implode('&', $parts);
        }

        return (string) $type;
    }
}
