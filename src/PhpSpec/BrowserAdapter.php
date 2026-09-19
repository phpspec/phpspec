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

namespace PhpSpec;

use Closure;
use PhpSpec\Browser\Browser;
use PhpSpec\Browser\Response;

/**
 * @internal
 * Wraps the active browser and reports each exchange through a callback: the
 * per-request one from the options, or the default it was built with. Every
 * browser implementation gets this behaviour without carrying it.
 */
final readonly class BrowserAdapter implements Browser
{
    /**
     * @param ?Closure(string, string, int, string, array<string, string>): void $callback
     */
    public function __construct(
        private Browser $inner,
        private ?Closure $callback = null,
    ) {}

    public function request(string $method, string $url, array $options = []): Response
    {
        $response = $this->inner->request($method, $url, $options);

        $callback = $options['callback'] ?? $this->callback;
        if (is_callable($callback)) {
            $callback($method, $url, $response->status, $response->body, $response->headers);
        }

        return $response;
    }
}
