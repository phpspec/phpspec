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

declare(strict_types=1);

namespace spec\PhpSpec\Factory;

use PhpSpec\Exception\Fracture\FactoryDoesNotReturnObjectException;
use PhpSpec\ObjectBehavior;

class ObjectFactorySpec extends ObjectBehavior
{
    function it_throws_exception_when_factory_method_returns_non_object()
    {
        $message = 'The method spec\PhpSpec\Factory\BrokenFactory::create did not return an object, returned NULL instead';
        $exception = new FactoryDoesNotReturnObjectException($message);
        $this->shouldThrow($exception)
            ->during('instantiateFromCallable', [
                [BrokenFactory::class, 'create']
            ]);
    }

    function it_throws_exception_when_factory_function_returns_non_object()
    {
        $message = 'The function spec\PhpSpec\Factory\brokenFactory did not return an object, returned NULL instead';
        $exception = new FactoryDoesNotReturnObjectException($message);
        $this->shouldThrow($exception)
            ->during('instantiateFromCallable', [
                'spec\PhpSpec\Factory\brokenFactory'
            ]);
    }
}

class BrokenFactory
{
    public static function create()
    {
        return null;
    }
}

function brokenFactory()
{
    return null;
}
