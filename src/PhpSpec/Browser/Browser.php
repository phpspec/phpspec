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

namespace PhpSpec\Browser;

/**
 * The browser behind visit() and friends. Client is the default; an extension
 * may drive anything else that maps its answers onto Response.
 */
interface Browser
{
    /**
     * @param array<string, mixed> $options request options (json, body, headers, callback, ...)
     */
    public function request(string $method, string $url, array $options = []): Response;
}
