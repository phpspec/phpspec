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

namespace PhpSpec\Extensions;

use PhpSpec\Result\Outcome;
use PhpSpec\Result\Outcomes;
use PhpSpec\Results;

/**
 * @internal
 * Tells listener extensions what the run sees: the suite beginning and ending,
 * and each example and scenario of every result it hears. Fed from the stream
 * of results the run reports, so stories, parallel workers and files that
 * failed to load reach the listeners the way they reach a formatter.
 */
final readonly class ListenerBridge
{
    /**
     * @param list<ListenerExtension> $extensions
     */
    public function __construct(
        private array $extensions,
        private Outcomes $walker,
    ) {}

    public function began(): void
    {
        foreach ($this->extensions as $extension) {
            $extension->beforeSuite();
        }
    }

    public function heard(Results $result): void
    {
        $this->walker->walk($result, function (Outcome $outcome): void {
            foreach ($this->extensions as $extension) {
                $extension->completed($outcome);
            }
        });
    }

    public function ended(): void
    {
        foreach ($this->extensions as $extension) {
            $extension->afterSuite();
        }
    }
}
