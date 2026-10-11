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
 * A double's stubs, in the order the spec declared them. A call the code
 * under spec makes uses up the first stub matching it that it has not used;
 * once every matching stub is used, the last one answers on. A call the spec
 * makes itself uses none up and is answered by no exception, so that writing
 * the next allow() of the same call declares it.
 */
final class Stubs
{
    /** @var array<string, list<array{arguments: array<int, mixed>|null, type: string, data: mixed, used: bool}>> */
    private array $stubs = [];

    /**
     * @param array<int, mixed>|null $arguments the arguments the stubbed call was written with, null for any
     * @param string $type value, callback or throw
     */
    public function add(string $method, ?array $arguments, string $type, mixed $data): void
    {
        $this->stubs[$method][] = ['arguments' => $arguments, 'type' => $type, 'data' => $data, 'used' => false];
    }

    /**
     * @param array<int, mixed> $arguments
     * @param bool $made whether the code under spec made the call, rather than the spec
     * @return array{type: string, data: mixed}|null
     */
    public function answer(string $method, array $arguments, bool $made): ?array
    {
        $next = null;
        $last = null;

        foreach ($this->stubs[$method] ?? [] as $position => $stub) {
            if ($stub['arguments'] !== null && !ArgumentMatcher::matchArgs($arguments, $stub['arguments'])) {
                continue;
            }

            $last = $position;
            $next ??= $stub['used'] ? null : $position;
        }

        $answering = $next ?? $last;

        if ($answering === null) {
            return null;
        }

        $stub = $this->stubs[$method][$answering];

        if (!$made) {
            return $stub['type'] === 'throw' ? null : ['type' => $stub['type'], 'data' => $stub['data']];
        }

        $this->stubs[$method][$answering]['used'] = true;

        return ['type' => $stub['type'], 'data' => $stub['data']];
    }
}
