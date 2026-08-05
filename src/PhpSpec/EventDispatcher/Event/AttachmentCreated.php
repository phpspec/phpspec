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

use Closure;
use PhpSpec\EventDispatcher\Event;

/**
 * @internal
 * Dispatched when a spec or a step hands over context only it can reach: the
 * output of a process it drove, the log the subject wrote, the page a browser
 * is showing. A closure is read later, when the failure is reported, so a value
 * that is still being written is current when it matters.
 */
final readonly class AttachmentCreated implements Event
{
    public const NAME = 'attachment.created';

    /**
     * @param string $name what to file it under; the same name twice is the same attachment, latest wins
     * @param string|Closure $value the text, or a closure read when the failure is reported
     */
    public function __construct(
        public string $name,
        public string|Closure $value,
    ) {}

    /** {@inheritdoc} */
    public function getName(): string
    {
        return self::NAME;
    }
}
