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
    function it_throws_exception_when_factory_static_method_returns_non_object()
    {
        $callable = [InvalidFactory::class, 'staticCreate'];
        $message = sprintf(
            'The method %s::%s did not return an object, returned NULL instead',
            $callable[0],
            $callable[1]
        );
        $exception = new FactoryDoesNotReturnObjectException($message);
        $this->shouldThrow($exception)->during(
            'instantiateFromCallable',
            [$callable]
        );
    }

    function it_throws_exception_when_factory_method_returns_non_object()
    {
        $callable = [new InvalidFactory(), 'create'];
        $message = sprintf(
            'The method %s::%s did not return an object, returned NULL instead',
            get_class($callable[0]),
            $callable[1]
        );
        $exception = new FactoryDoesNotReturnObjectException($message);
        $this->shouldThrow($exception)->during(
            'instantiateFromCallable',
            [$callable]
        );
    }

    function it_throws_exception_when_closure_returns_null()
    {
        $closure = \Closure::fromCallable('spec\PhpSpec\Factory\invalidFactory');
        $message = 'The closure did not return an object, returned NULL instead';
        $exception = new FactoryDoesNotReturnObjectException($message);
        $this->shouldThrow($exception)->during(
            'instantiateFromCallable',
            [$closure]
        );
    }

    function it_throws_exception_when_factory_function_returns_non_object()
    {
        $callable = '\spec\PhpSpec\Factory\invalidFactory';
        $message = sprintf(
            'The function %s did not return an object, returned NULL instead',
            $callable
        );
        $exception = new FactoryDoesNotReturnObjectException($message);
        $this->shouldThrow($exception)->during(
            'instantiateFromCallable',
            [$callable]
        );
    }

    function it_does_not_throw_exception_when_factory_static_method_returns_non_object()
    {
        $callable = [ValidFactory::class, 'staticCreate'];
        $this->shouldNotThrow(FactoryDoesNotReturnObjectException::class)->during(
            'instantiateFromCallable',
            [$callable]
        );
    }

    function it_does_not_throw_exception_when_factory_method_returns_non_object()
    {
        $callable = [new ValidFactory(), 'create'];
        $this->shouldNotThrow(FactoryDoesNotReturnObjectException::class)->during(
            'instantiateFromCallable',
            [$callable]
        );
    }

    function it_does_not_throw_exception_when_closure_returns_null()
    {
        $closure = \Closure::fromCallable('spec\PhpSpec\Factory\validFactory');
        $this->shouldNotThrow(FactoryDoesNotReturnObjectException::class)->during(
            'instantiateFromCallable',
            [$closure]
        );
    }

    function it_does_not_throw_exception_when_factory_function_returns_non_object()
    {
        $callable = '\spec\PhpSpec\Factory\validFactory';
        $this->shouldNotThrow(FactoryDoesNotReturnObjectException::class)->during(
            'instantiateFromCallable',
            [$callable]
        );
    }
}

class InvalidFactory
{
    public static function staticCreate(): ?object
    {
        return null;
    }

    public function create(): ?object
    {
        return static::staticCreate();
    }
}

class ValidFactory extends InvalidFactory
{
    public static function staticCreate(): ?object
    {
        return (object)parent::staticCreate();
    }

    public function create(): ?object
    {
        return (object)parent::create();
    }
}

function invalidFactory()
{
    return InvalidFactory::staticCreate();
}

function validFactory()
{
    return ValidFactory::staticCreate();
}
