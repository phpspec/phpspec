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

namespace PhpSpec\EventDispatcher\Event\Mock;

use PhpSpec\EventDispatcher\Event;
use PhpSpec\Mock\MockedMethod;

/**
 * Dispatched when a mocked method is invoked on a test double during example execution.
 */
final readonly class MethodMocked implements Event
{
    public const NAME = 'method.mocked';

    /**
     * @param mixed $double the mock double instance
     * @param MockedMethod $mockedMethod the method descriptor that was called
     * @param mixed $prediction the expected behavior or return value
     */
    public function __construct(private mixed $double, private MockedMethod $mockedMethod, private mixed $prediction) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the mock double instance.
     */
    public function getDouble(): mixed
    {
        return $this->double;
    }

    /**
     * Returns the mocked method descriptor.
     */
    public function getMockedMethod(): MockedMethod
    {
        return $this->mockedMethod;
    }

    /**
     * Returns the prediction (expected behavior or return value) for the method call.
     */
    public function getPrediction(): mixed
    {
        return $this->prediction;
    }
}
