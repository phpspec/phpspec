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

use IteratorAggregate;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * @internal
 * What a double answers to a call nobody stubbed of a method returning a
 * class or an interface: an answer of that type standing for the call, for
 * allow() and expect() to take, whose methods refuse to be used; on a lenient
 * double, a dummy of the type instead.
 */
final class Answers
{
    /** @var array<string, class-string<DummyAnswer>> The class of a dummy answer, by the types it is of */
    private static array $dummies = [];

    public static function reset(): void
    {
        self::$dummies = [];
    }

    /**
     * What a lenient double answers to a call of a method returning the given
     * types: a dummy of them, standing for the call. What an aggregate's
     * getIterator() answers is an empty Iterator, which foreach ends at once,
     * where another aggregate could lead to one more without end; one that
     * cannot be is refused, saying what keeps it from being one.
     *
     * @param non-empty-list<class-string> $types a class first, if any, then interfaces
     */
    public static function dummy(GeneratedDouble $double, string $method, array $types): object
    {
        $emptyIterator = $double instanceof IteratorAggregate && strcasecmp($method, 'getIterator') === 0;
        $reflections = array_map(static fn(string $type): ReflectionClass => new ReflectionClass($type), $types);
        $key = implode('&', $types) . ($emptyIterator ? ' as an empty iterator' : '');

        if (!isset(self::$dummies[$key])) {
            [$base, $methods, $refusal] = Double::planOf($reflections, $emptyIterator);

            if ($methods === null) {
                throw new LogicException(sprintf('PhpSpec cannot stand in for the return type of %s::%s(): no %s of %s can be made. %s', ltrim($double->______PhpSpecNameOfClassDoubled(), '\\'), $method, $emptyIterator ? 'empty iterator' : 'double', implode('&', $types), $refusal));
            }

            self::$dummies[$key] = self::dummyClassOf($reflections, $base, $methods);
        }

        $answerClass = new ReflectionClass(self::$dummies[$key]);
        $answer = $reflections[0]->isInterface() ? $answerClass->newInstance() : $answerClass->newInstanceWithoutConstructor();
        $answer->______PhpSpecAnswering($double, $method);

        return $answer;
    }

    /**
     * The class of what a call nobody stubbed answers when its method returns
     * a class or an interface, or a union of them alone: one of the first type
     * that can have one, there to be handed along, refusing to be used, and
     * the types it is of, for a lenient double to answer a dummy of instead.
     * With no class, the reason PHP lets no class be the type, when it gives
     * one.
     *
     * @return array{0: string|null, 1: string|null, 2: list<class-string>}
     */
    public static function classFor(ReflectionType $type, string $methodName, string $suffix): array
    {
        $members = $type instanceof ReflectionUnionType
            ? ($type->allowsNull() || self::holdsScalar($type) ? [] : $type->getTypes())
            : [$type];
        $refusal = null;

        foreach ($members as $member) {
            $types = self::typesOf($member);

            if ($types === null) {
                continue;
            }

            $names = array_map(static fn(ReflectionClass $type): string => $type->getName(), $types);

            if (DeclaredType::allOf($names)->admitsNoValue()) {
                $last = array_pop($names);
                $refusal ??= sprintf('No object can be %s %s and %s.', count($names) === 1 ? 'both' : 'all of', implode(', ', $names), $last);

                continue;
            }

            [$className, $refused] = self::answerClass($types, $methodName, $suffix);

            if ($className !== null) {
                return [$className, null, array_map(static fn(ReflectionClass $type): string => $type->getName(), $types)];
            }

            $refusal ??= $refused;
        }

        return [null, $refusal, []];
    }

    /**
     * The class of what a method with no return type answers: a handle on the
     * call, for allow() and expect().
     */
    public static function untypedClass(string $methodName, string $suffix): string
    {
        $className = 'MockedMethodFor' . $suffix;

        eval(self::wrapperCode($className, null, [], false, $methodName, ''));

        return $className;
    }

    /**
     * Generates the class of a dummy answer of the given types: a double of
     * them that stands for the call it answered.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return class-string<DummyAnswer>
     * @throws ReflectionException
     */
    private static function dummyClassOf(array $types, ?NativeBase $base, MethodSet $methods): string
    {
        $doubleClass = Double::classOf($types, $base, $methods);
        $shortName = substr($doubleClass, strlen('PhpspecDouble\\')) . '_Answer';

        eval("namespace PhpspecDouble; class $shortName extends \\$doubleClass implements \\PhpSpec\\Mock\\MatchableDouble, \\PhpSpec\\Mock\\DummyAnswer { use \\PhpSpec\\Mock\\MatchableDoubleBehaviour, \\PhpSpec\\Mock\\DummyAnswerBehaviour; }");
        $answerClass = 'PhpspecDouble\\' . $shortName;

        if (!is_a($answerClass, DummyAnswer::class, true)) {
            throw new LogicException("Generated answer class '$answerClass' is not a dummy answer.");
        }

        return $answerClass;
    }

    /**
     * The class of an answer of the given types: a subclass of a class that
     * can have one, built without its constructor, implementing the
     * interfaces, on the base PHP requires for them.
     *
     * @param non-empty-list<ReflectionClass<object>> $types a class first, if any, then interfaces
     * @return array{0: string|null, 1: string|null} the class, or the reason no class can be the types
     */
    private static function answerClass(array $types, string $methodName, string $suffix): array
    {
        $first = $types[0];

        if (!$first->isInterface() && ($first->isFinal() || $first->isReadOnly())) {
            return [null, null];
        }

        [$base, $methods, $refusal] = Double::planOf($types, false, false);

        if ($methods === null) {
            return [null, $refusal];
        }

        foreach ($types as $type) {
            if ($type->isInterface() && $type->isInternal() && (count($types) === 1 || !in_array($type->getName(), NativeBase::HANDLED, true))) {
                return [null, null];
            }
        }

        $className = 'MockedMethodFor' . $suffix;
        $interfaces = array_map(static fn(ReflectionClass $type): string => '\\' . $type->getName(), array_values(array_filter($types, static fn(ReflectionClass $type): bool => $type->isInterface())));
        $extends = $base?->extends() ?? ($first->isInterface() ? null : '\\' . $first->getName());

        return [self::evaluated($className, self::wrapperCode($className, $extends, [...$interfaces, ...($base?->implements() ?? [])], $first->isInterface() && $base?->extends() !== null, $methodName, self::answerMethods($methods->contracts, $base))), null];
    }

    /**
     * The methods of an answer: each the types declare, refusing to be used.
     *
     * @param list<MethodContract> $contracts
     */
    private static function answerMethods(array $contracts, ?NativeBase $base): string
    {
        $methods = [];

        foreach ($contracts as $contract) {
            $methods[] = sprintf(
                '    public function %s%s(%s)%s { throw $this->______PhpSpecUsed(%s, %s, \\func_get_args(), \\debug_backtrace(\\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? []); }',
                $contract->byReference ? '&' : '',
                $contract->name,
                $contract->parametersCode()[0],
                $contract->returnCode,
                var_export($contract->owner, true),
                var_export($contract->name, true),
            );
        }

        return implode("\n", $methods) . ($base?->code() ?? '');
    }

    /**
     * The classes and interfaces a type names, or null when it names
     * something else: a built-in type, an enum, a type that does not exist.
     *
     * @return non-empty-list<ReflectionClass<object>>|null
     */
    private static function typesOf(ReflectionType $type): ?array
    {
        $named = $type instanceof ReflectionIntersectionType ? $type->getTypes() : [$type];
        $types = [];

        foreach ($named as $member) {
            if (!$member instanceof ReflectionNamedType || $member->isBuiltin()) {
                return null;
            }

            $name = $member->getName();

            if ((!class_exists($name) && !interface_exists($name)) || enum_exists($name)) {
                return null;
            }

            $types[] = new ReflectionClass($name);
        }

        usort($types, static fn(ReflectionClass $one, ReflectionClass $other): int => $one->isInterface() <=> $other->isInterface());

        return $types === [] ? null : $types;
    }

    private static function holdsScalar(ReflectionUnionType $type): bool
    {
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionNamedType && in_array($member->getName(), ['string', 'int', 'float', 'bool', 'array'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An answer's class: a MatchableDouble standing for the call that
     * answered it, of the class it extends and the interfaces it implements,
     * initialised as a base PHP requires is.
     *
     * @param list<string> $interfaces
     */
    private static function wrapperCode(string $className, ?string $extends, array $interfaces, bool $initialised, string $methodName, string $methods): string
    {
        $extends = $extends === null ? '' : 'extends ' . $extends . ' ';
        $implements = implode(', ', ['\\PhpSpec\\Mock\\MatchableDouble', ...$interfaces]);
        $initialised = $initialised ? 'parent::__construct();' : '';

        return <<<PHP
class $className {$extends}implements $implements {
    use \\PhpSpec\\Mock\\MatchableDoubleBehaviour;

    public function __construct(object \$mockedObject) {
        $initialised
        \$this->______phpspec_mockedObject = \$mockedObject;
        \$this->______phpspec_methodName = '$methodName';
    }

    $methods
}
PHP;
    }

    /**
     * The class, once its code is evaluated, or null when PHP refuses it.
     */
    private static function evaluated(string $className, string $code): ?string
    {
        try {
            eval($code);
        } catch (Throwable) {
            return null;
        }

        return $className;
    }
}
