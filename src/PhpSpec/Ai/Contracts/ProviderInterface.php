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

use PhpSpec\Ai\Response;

/**
 * PhpSpec's AI provider interface.
 *
 * Abstracts the LLM provider so PhpSpec does not depend on any specific
 * AI library at runtime.
 */
interface ProviderInterface
{
    /**
     * Sends a chat completion request to the LLM.
     *
     * @param \PhpSpec\Ai\Message[] $messages conversation messages
     * @param array<string, mixed> $options provider options (model, maxTokens, temperature, tools, etc.)
     *
     * @return Response
     */
    public function chat(array $messages, array $options = []): Response;
}
