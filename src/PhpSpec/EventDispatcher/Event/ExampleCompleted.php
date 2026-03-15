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
use PhpSpec\Result\ExampleResult;

/**
 * Dispatched when an example finishes execution, carrying the result.
 */
final readonly class ExampleCompleted implements Event, Loggable
{
    use LogDateTime;
    public const NAME = 'example.completed';

    /**
     * @param string $title the example description
     * @param ExampleResult $result the result of the example execution
     */
    public function __construct(
        private string $title,
        private ExampleResult $result,
    ) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * Returns the example description.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Returns the result of the example execution.
     *
     * @return ExampleResult
     */
    public function getResult(): ExampleResult
    {
        return $this->result;
    }

    /** {@inheritdoc} */
    public function getLog(): string
    {
        return $this->getLogNow($this->getName() . ' - ' . $this->title);
    }
}
