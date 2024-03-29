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

namespace PhpSpec\CodeAnalysis;

interface AccessInspector
{
    public function isPropertyReadable(object $object, string $property): bool;

    public function isPropertyWritable(object $object, string $property): bool;

    public function isMethodCallable(object $object, string $method): bool;
}
