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

namespace PhpSpec;

use Closure;
use LogicException;
use ReflectionFunction;

/**
 * @internal
 * A function the spec gives PhpSpec to call with nothing: a describe() or
 * context() block, a beforeAll() or afterAll(), a story hook.
 */
final readonly class ArgumentlessFunction
{
    public function __construct(private Closure $function) {}

    public function __invoke(): mixed
    {
        $this->refuseArguments();

        return ($this->function)();
    }

    public function callOn(object $world): mixed
    {
        $this->refuseArguments();

        return $this->function->call($world);
    }

    private function refuseArguments(): void
    {
        if ((new ReflectionFunction($this->function))->getNumberOfRequiredParameters() > 0) {
            throw new LogicException('Arguments not allowed in this function, only this(), it(), beforeEach() and afterEach() can take doubles.');
        }
    }
}
