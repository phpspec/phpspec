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

namespace PhpSpec\Specification;

use Closure;
use PhpSpec\Mock\Double;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * @internal
 * What the closures of one example are handed for their parameters typed with
 * a class or interface: a double of the type, the same for every closure that
 * names the parameter alike. A this() puts the doubles it takes on $this under
 * their names too, unless a this() has the name.
 */
final class ExampleArguments
{
    /** @var array<string, object> */
    private array $doubles = [];

    /** @var list<string> */
    private array $placed = [];

    /**
     * @param list<string> $bound the names of the example's this() values
     */
    public function __construct(
        private readonly Subject $world = new Subject(),
        private readonly array $bound = [],
    ) {}

    /**
     * @return list<object>
     */
    public function for(Closure $closure): array
    {
        return array_values($this->doublesFor($closure));
    }

    /**
     * @return list<object>
     */
    public function forThis(Closure $closure): array
    {
        $doubles = $this->doublesFor($closure);

        foreach (array_diff_key($doubles, array_flip($this->bound)) as $name => $double) {
            $this->world->$name = $double;
            $this->placed[] = $name;
        }

        return array_values($doubles);
    }

    /**
     * Takes off $this the doubles it put there, so no later example finds one
     * that carries this example's stubs.
     */
    public function forget(): void
    {
        foreach ($this->placed as $name) {
            unset($this->world->$name);
        }
    }

    /**
     * @return array<string, object>
     */
    private function doublesFor(Closure $closure): array
    {
        $doubles = [];

        foreach ((new ReflectionFunction($closure))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $doubles[$parameter->getName()] = $this->double($parameter->getName(), $type->getName());
            }
        }

        return $doubles;
    }

    private function double(string $name, string $type): object
    {
        if (!isset($this->doubles[$name]) || !$this->doubles[$name] instanceof $type) {
            $this->doubles[$name] = Double::getInstance($type);
        }

        return $this->doubles[$name];
    }
}
