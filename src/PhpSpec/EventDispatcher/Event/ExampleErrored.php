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
use PhpSpec\Specification\ExampleError;

/**
 * @internal
 * Dispatched when an example throws an unexpected error or exception during execution.
 */
final readonly class ExampleErrored implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'example.errored';

    /**
     * @param string $title the example description
     * @param ExampleError $error the error that occurred
     */
    public function __construct(private string $title, private ExampleError $error) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the example description.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the error that caused the example to fail.
     */
    public function getError(): ExampleError
    {
        return $this->error;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow($this->getName() . ' - ' . $this->getTitle());
    }
}
