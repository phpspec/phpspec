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

use PhpSpec\Ai\Contracts\ToolInterface;

/**
 * Interface for extensions that provide AI tools for pair mode.
 */
interface ToolProviderInterface
{
    /**
     * Returns the tools to register with the AI assistant.
     *
     * @return ToolInterface[]
     */
    public function getTools(): array;
}
