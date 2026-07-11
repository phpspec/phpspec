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

namespace PhpSpec\Specification;

/**
 * @internal
 * Minimal scope used while parsing a spec file: it captures the top-level
 * blocks that describe()/it() register, without running them.
 */
final class BlockCollector implements ExampleRegistry
{
    /** @var list<SpecBlock> top-level blocks registered during the parse */
    private array $blocks = [];

    /**
     * Records a top-level spec block registered during the parse.
     *
     * @param SpecBlock $example the block to collect
     * @return void
     */
    public function addSpecBlock(SpecBlock $example): void
    {
        $this->blocks[] = $example;
    }

    /**
     * Returns the collected top-level blocks in registration order.
     *
     * @return list<SpecBlock>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }
}
