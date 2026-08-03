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

namespace PhpSpec\Report\Formatter\Agent;

/**
 * @internal
 * Where an entry came from: the path of titles that leads to it, and the file
 * and line that address it. Built up as the result tree is walked, so a step
 * knows the scenario it belongs to and a scenario knows the line to re-run.
 *
 * The titles join the way the pretty and dot output already joins them, so one
 * failure has one name whichever formatter reports it.
 */
final readonly class Origin
{
    /**
     * @param string $name the titles leading here, joined
     * @param string|null $path the file this came from, when it is addressable
     * @param int|null $line the line within that file, when one is known
     */
    public function __construct(
        public string $name = '',
        public ?string $path = null,
        public ?int $line = null,
    ) {}

    /**
     * The origin one level in: the title joins the path, and a file or line
     * given here addresses everything below it.
     *
     * @param string $title the title of the level being entered
     * @param string|null $path the file it lives in, or null to keep the current one
     * @param int|null $line the line it starts at, or null to keep the current one
     */
    public function within(string $title, ?string $path = null, ?int $line = null): self
    {
        return new self(
            $this->name === '' ? $title : $this->name . ' > ' . $title,
            $path ?? $this->path,
            $line ?? $this->line,
        );
    }

    /**
     * The full name of an entry with this title: every title above it, then its
     * own.
     */
    public function name(string $title): string
    {
        return $this->within($title)->name;
    }
}
