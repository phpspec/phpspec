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
use ReflectionType;
use ReflectionUnionType;

/**
 * @internal
 * What a doubled method returns, held strictly against what a stub gives it:
 * a value the real method could never return is refused where the spec sets
 * it, naming the real method, not the double's generated class.
 */
final readonly class ReturnType
{
    private string $class;

    private ReflectionMethod $method;

    /**
     * @param string $class the doubled class or interface
     */
    public function __construct(string $class, string $method)
    {
        $this->class = ltrim($class, '\\');
        $this->method = new ReflectionMethod($this->class, $method);
    }

    /**
     * @throws LogicException when the method could never return the value
     */
    public function holdStub(mixed $value): void
    {
        $type = $this->type();

        if ($type instanceof ReflectionNamedType && $type->getName() === 'void') {
            throw new LogicException(sprintf('%s() returns void, so toReturn() has nothing to give it: declare the call with allow() alone.', $this->name()));
        }

        if (!$this->admits($type, $value)) {
            throw new LogicException(sprintf('%s() returns %s, so it cannot return %s.', $this->name(), $type, $this->shown($value)));
        }
    }

    /**
     * @throws LogicException when a stub's callback answered what the method could never return
     */
    public function holdAnswer(mixed $value): void
    {
        $type = $this->type();

        if (!$this->admits($type, $value)) {
            throw new LogicException(sprintf('%s() returns %s, so its toReturnUsing() callback cannot answer %s.', $this->name(), $type, $this->shown($value)));
        }
    }

    private function type(): ?ReflectionType
    {
        return $this->method->getReturnType() ?? $this->method->getTentativeReturnType();
    }

    private function admits(?ReflectionType $type, mixed $value): bool
    {
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($this->admits($member, $value)) {
                    return true;
                }
            }

            return false;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) {
                if (!$this->admits($member, $value)) {
                    return false;
                }
            }

            return true;
        }

        if (!$type instanceof ReflectionNamedType) {
            return true;
        }

        if ($value === null) {
            return $type->allowsNull();
        }

        return match ($type->getName()) {
            'mixed' => true,
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool' => is_bool($value),
            'true' => $value === true,
            'false' => $value === false,
            'array' => is_array($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            'object' => is_object($value),
            'void', 'never', 'null' => false,
            'self' => $value instanceof $this->method->class,
            'static' => $value instanceof $this->class,
            'parent' => ($parent = $this->method->getDeclaringClass()->getParentClass()) !== false && $value instanceof $parent->name,
            default => $value instanceof ($type->getName()),
        };
    }

    private function shown(mixed $value): string
    {
        return match (true) {
            is_string($value) => sprintf('string "%s"', $value),
            is_bool($value) => 'bool ' . ($value ? 'true' : 'false'),
            is_int($value), is_float($value) => get_debug_type($value) . ' ' . var_export($value, true),
            default => get_debug_type($value),
        };
    }

    private function name(): string
    {
        return $this->class . '::' . $this->method->name;
    }
}
