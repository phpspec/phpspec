<?php

/*
 * This file is part of PhpSpec, A php toolset to drive emergent
 * design by specification.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpSpec\Formatter\Presenter\Differ;

class Differ
{
    public function __construct(
        private array $engines = []
    )
    {
    }

    public function addEngine(DifferEngine $engine): void
    {
        $this->engines[] = $engine;
    }

    public function compare(mixed $expected, mixed $actual) : ?string
    {
        foreach ($this->engines as $engine) {
            if ($engine->supports($expected, $actual)) {
                return rtrim($engine->compare($expected, $actual));
            }
        }

        return null;
    }
}
