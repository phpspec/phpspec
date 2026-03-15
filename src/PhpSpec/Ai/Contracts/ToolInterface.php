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

namespace PhpSpec\Ai\Contracts;

/**
 * Interface for AI tools that can be invoked by an LLM.
 */
interface ToolInterface
{
    /**
     * Returns the tool name used in API calls.
     */
    public function getName(): string;

    /**
     * Returns a description of the tool for the LLM.
     */
    public function getDescription(): string;

    /**
     * Returns the JSON Schema for the tool's parameters.
     *
     * @return array<string, mixed>
     */
    public function getParameterSchema(): array;

    /**
     * Executes the tool with the given arguments.
     *
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments, mixed $context = null): mixed;
}
