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

namespace PhpSpec\StoryBDD;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/**
 * @internal
 * The parameter types and doc string types the support code declared, and
 * the arguments they make for a step from its captures and doc string.
 */
final readonly class StepTypes
{
    /**
     * @param array<string, ReflectionFunction> $parameterTypes the function making each class
     * @param array<string, ReflectionFunction> $docStringTypes the function for each doc string type
     */
    public function __construct(
        private array $parameterTypes = [],
        private array $docStringTypes = [],
    ) {}

    /**
     * The types the functions declared in the given files stand for.
     */
    public static function declaredIn(string ...$files): self
    {
        $files = array_flip(array_map(self::plainly(...), $files));

        return self::of(...array_filter(
            array_map(static fn(string $function): ReflectionFunction => new ReflectionFunction($function), get_defined_functions()['user']),
            static fn(ReflectionFunction $function): bool => isset($files[self::plainly((string) $function->getFileName())]),
        ));
    }

    /**
     * The types the given functions stand for, those carrying a type attribute.
     *
     * @throws RuntimeException when two functions stand for one type, or one cannot stand for any
     */
    public static function of(ReflectionFunction ...$functions): self
    {
        $parameterTypes = [];
        $docStringTypes = [];

        foreach ($functions as $function) {
            if ($function->getAttributes(ParameterType::class) !== []) {
                $class = self::classMadeBy($function);
                self::refuseSecond($parameterTypes[$class] ?? null, $function, $class);
                $parameterTypes[$class] = $function;
            }

            foreach ($function->getAttributes(DocStringType::class) as $attribute) {
                $name = $attribute->newInstance()->name;

                if ($function->getNumberOfParameters() !== 1) {
                    throw new RuntimeException(sprintf('#[DocStringType] %s() must take one parameter.', $function->getName()));
                }

                self::refuseSecond($docStringTypes[$name] ?? null, $function, sprintf('the "%s" doc string', $name));
                $docStringTypes[$name] = $function;
            }
        }

        return new self($parameterTypes, $docStringTypes);
    }

    /**
     * The arguments a step closure is called with: each parameter a type makes
     * takes as many captures as its function does, none included, any other
     * the next capture as it is, and the doc string goes through the type its
     * parameter is named after; the table comes last.
     *
     * @param list<string> $captures
     * @return list<mixed>
     *
     * @throws RuntimeException when a type takes more captures than the step has left
     */
    public function arguments(Closure $step, array $captures, ?string $docString, ?DataTable $table): array
    {
        $parameters = (new ReflectionFunction($step))->getParameters();
        $arguments = [];
        $next = 0;

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic()) {
                break;
            }

            $make = $this->parameterTypeOf($parameter);

            if ($make === null) {
                if ($next >= count($captures)) {
                    break;
                }

                $arguments[] = $captures[$next++];

                continue;
            }

            $takes = $make->getNumberOfParameters();

            if ($next + $takes > count($captures)) {
                throw new RuntimeException(sprintf('%s() takes %d captures, the step has %d left.', $make->getName(), $takes, count($captures) - $next));
            }

            $arguments[] = $make->invokeArgs(array_slice($captures, $next, $takes));
            $next += $takes;
        }

        array_push($arguments, ...array_slice($captures, $next));

        if ($docString !== null) {
            $named = ($parameters[count($arguments)] ?? null)?->getName();
            $turn = $named === null ? null : $this->docStringTypes[$named] ?? null;
            $arguments[] = $turn === null ? $docString : $turn->invoke($docString);
        }

        if ($table !== null) {
            $arguments[] = $table;
        }

        return $arguments;
    }

    private static function plainly(string $file): string
    {
        return realpath($file) ?: $file;
    }

    private function parameterTypeOf(ReflectionParameter $parameter): ?ReflectionFunction
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType ? $this->parameterTypes[$type->getName()] ?? null : null;
    }

    private static function classMadeBy(ReflectionFunction $function): string
    {
        $type = $function->getReturnType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            throw new RuntimeException(sprintf('#[ParameterType] %s() returns no class.', $function->getName()));
        }

        return $type->getName();
    }

    private static function refuseSecond(?ReflectionFunction $first, ReflectionFunction $second, string $made): void
    {
        if ($first !== null) {
            throw new RuntimeException(sprintf('%s() and %s() both make %s.', $first->getName(), $second->getName(), $made));
        }
    }
}
