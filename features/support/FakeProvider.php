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

namespace PhpSpec\Acceptance\Support;

use PhpSpec\Ai\Contracts\ProviderInterface;
use PhpSpec\Ai\Response;

/**
 * The AI provider a scenario gets with "Given a fake AI provider": no
 * provider package, no network, the same line of prose for every request.
 */
final class FakeProvider implements ProviderInterface
{
    public function chat(array $messages, array $options = []): Response
    {
        return new Response('The fake provider has nothing to add.');
    }
}
