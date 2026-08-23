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

use RuntimeException;

/**
 * @internal
 * Holds the active browser and the base URL, and resolves each request
 * through them. Knows nothing about where either came from.
 */
final class BrowserRegistry
{
    private static ?Browser $browser = null;

    private static ?string $baseUrl = null;

    public static function use(Browser $browser): void
    {
        self::$browser = $browser;
    }

    public static function init(string $baseUrl): void
    {
        self::$baseUrl = $baseUrl;
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function request(string $method, string $path, array $options = []): Response
    {
        self::$browser ??= new Client();

        return self::$browser->request($method, self::resolve($path), $options);
    }

    public static function reset(): void
    {
        self::$browser = null;
        self::$baseUrl = null;
    }

    /**
     * @return array{browser: Browser|null, baseUrl: string|null}
     */
    public static function saveState(): array
    {
        return ['browser' => self::$browser, 'baseUrl' => self::$baseUrl];
    }

    /**
     * @param array{browser: Browser|null, baseUrl: string|null} $state
     */
    public static function restoreState(array $state): void
    {
        self::$browser = $state['browser'];
        self::$baseUrl = $state['baseUrl'];
    }

    private static function resolve(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        if (self::$baseUrl === null) {
            throw new RuntimeException('No base URL configured: call BrowserRegistry::init() with one.');
        }

        return rtrim(self::$baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
