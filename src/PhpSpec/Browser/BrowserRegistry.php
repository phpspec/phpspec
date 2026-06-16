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

use PhpSpec\Configuration;
use RuntimeException;

/**
 * @internal Not part of the public API.
 *
 * Static registry holding the Browser Client instance.
 * Lazily creates the client from Configuration on first access.
 */
final class BrowserRegistry
{
    private static ?Client $client = null;

    /**
     * Returns the HTTP client, creating it from configuration if needed.
     *
     * @throws RuntimeException if base_url is not configured
     */
    public static function client(): Client
    {
        if (self::$client === null) {
            self::init();
        }

        if (self::$client === null) {
            throw new RuntimeException('Browser client failed to initialize');
        }

        return self::$client;
    }

    /**
     * Loads configuration and creates the Client from base_url.
     *
     * @throws RuntimeException if base_url is not configured
     */
    public static function init(): void
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Unable to determine current working directory');
        }
        $config = new Configuration($cwd);
        $baseUrl = $config->getBaseUrl();

        if ($baseUrl === null) {
            throw new RuntimeException(
                'Browser testing requires "base_url" in phpspec.json. Example: {"base_url": "http://localhost:8080"}',
            );
        }

        self::$client = new Client($baseUrl);
    }

    /**
     * Resets the client for test isolation.
     */
    public static function reset(): void
    {
        self::$client = null;
    }

    /**
     * Captures the current client for later restoration.
     *
     * @return array{client: Client|null}
     */
    public static function saveState(): array
    {
        return ['client' => self::$client];
    }

    /**
     * Restores a previously saved client state.
     *
     * @param array{client: Client|null} $state
     */
    public static function restoreState(array $state): void
    {
        self::$client = $state['client'];
    }
}
