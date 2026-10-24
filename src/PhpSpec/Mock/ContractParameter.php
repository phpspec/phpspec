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

use ReflectionParameter;

/**
 * @internal
 * A parameter of a double's method, as every declaration of the method
 * requires it.
 */
final readonly class ContractParameter
{
    /**
     * @param ReflectionParameter|null $source the declared parameter it takes its default from, null for a method only a tag declares
     * @param bool $optional whether a call any declaration takes may leave it out
     * @param string $declared its type as the declarations write it, for a message; empty when untyped
     * @param string|null $takes the type an argument is held to, as code; null when any value goes
     */
    public function __construct(
        public ?ReflectionParameter $source,
        public string $name,
        public int $position,
        public bool $byReference,
        public bool $variadic,
        public bool $optional,
        public string $declared,
        public ?string $takes,
    ) {}

    public function hasDefault(): bool
    {
        return !$this->variadic && $this->source !== null && $this->source->isDefaultValueAvailable();
    }

    public function default(): mixed
    {
        return $this->source?->getDefaultValue();
    }
}
