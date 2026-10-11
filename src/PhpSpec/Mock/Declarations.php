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

/**
 * @internal
 * The calls allow() and expect() declared on a double: the code under spec
 * may make them.
 */
final class Declarations
{
    /** @var array<string, list<array<int, mixed>|null>> */
    private array $declared = [];

    /**
     * @param array<int, mixed>|null $arguments the arguments the call was written with, null for any
     */
    public function add(string $method, ?array $arguments): void
    {
        $this->declared[$method][] = $arguments;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function cover(string $method, array $arguments): bool
    {
        foreach ($this->declared[$method] ?? [] as $declared) {
            if ($declared === null || ArgumentMatcher::matchArgs($arguments, $declared)) {
                return true;
            }
        }

        return false;
    }
}
