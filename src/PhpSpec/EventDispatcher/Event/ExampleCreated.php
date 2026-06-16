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

namespace PhpSpec\EventDispatcher\Event;

use PhpSpec\EventDispatcher\Event;
use PhpSpec\Logging\LogDateTime;
use PhpSpec\Logging\Loggable;
use PhpSpec\Specification\Example;

/**
 * @internal
 * Dispatched when an example is created via the it() DSL function during spec loading.
 */
final readonly class ExampleCreated implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'example.created';

    /**
     * @param string $title the example description
     * @param Example $example the newly created example
     */
    public function __construct(private string $title, private Example $example) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the example that was created.
     */
    public function getExample(): Example
    {
        return $this->example;
    }

    /**
     * Returns the example description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow($this->getName() . ' - ' . $this->getTitle());
    }
}
