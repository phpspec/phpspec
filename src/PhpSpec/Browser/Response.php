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

use InvalidArgumentException;

/**
 * Immutable HTTP response value object returned by the Browser DSL functions.
 *
 * @property-read array<string, mixed> $json JSON-decoded body (lazy, empty array for non-JSON)
 */
final class Response
{
    /** @var array<string, mixed>|null cached JSON decode result */
    private ?array $decodedJson = null;

    /**
     * Creates a new immutable HTTP response.
     *
     * @param int $status HTTP status code
     * @param string $body raw response body
     * @param array<string, string> $headers response headers (name => value)
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    /**
     * Provides virtual property access; currently supports 'json' for decoded body.
     *
     * @param string $name virtual property name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if ($name === 'json') {
            return $this->decodedJson ??= json_decode($this->body, true) ?? [];
        }

        throw new InvalidArgumentException("Undefined property: $name");
    }

    /**
     * Returns true for supported virtual properties.
     *
     * @param string $name virtual property name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $name === 'json';
    }
}
