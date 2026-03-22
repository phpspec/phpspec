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

use PhpSpec\Browser\Client;
use PhpSpec\Browser\Response;
use PhpSpec\Configuration;

/**
 * Sends a GET request to the given path. Alias for get().
 *
 * @param string $path URL path
 *
 * @return Response
 */
function visit(string $path): Response
{
    return BrowserRegistry::client()->request('GET', $path);
}

/**
 * Sends a GET request to the given path.
 *
 * @param string $path URL path
 *
 * @return Response
 */
function get(string $path): Response
{
    return BrowserRegistry::client()->request('GET', $path);
}

/**
 * Sends a POST request to the given path.
 *
 * @param string $path URL path
 * @param array{json?: array<string, mixed>, body?: string, headers?: array<string, string>} $options request options
 *
 * @return Response
 */
function post(string $path, array $options = []): Response
{
    return BrowserRegistry::client()->request('POST', $path, $options);
}

/**
 * Sends a PUT request to the given path.
 *
 * @param string $path URL path
 * @param array{json?: array<string, mixed>, body?: string, headers?: array<string, string>} $options request options
 *
 * @return Response
 */
function put(string $path, array $options = []): Response
{
    return BrowserRegistry::client()->request('PUT', $path, $options);
}

/**
 * Sends a PATCH request to the given path.
 *
 * @param string $path URL path
 * @param array{json?: array<string, mixed>, body?: string, headers?: array<string, string>} $options request options
 *
 * @return Response
 */
function patch(string $path, array $options = []): Response
{
    return BrowserRegistry::client()->request('PATCH', $path, $options);
}

/**
 * Sends a DELETE request to the given path.
 *
 * @param string $path URL path
 * @param array{json?: array<string, mixed>, body?: string, headers?: array<string, string>} $options request options
 *
 * @return Response
 */
function delete(string $path, array $options = []): Response
{
    return BrowserRegistry::client()->request('DELETE', $path, $options);
}

/**
 * Static registry holding the Browser Client instance.
 * Lazily creates the client from Configuration on first access.
 */
class BrowserRegistry
{
    private static ?Client $client = null;

    /**
     * Returns the HTTP client, creating it from configuration if needed.
     *
     * @throws RuntimeException if base_url is not configured
     *
     * @return Client
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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
     */
    public static function restoreState(array $state): void
    {
        self::$client = $state['client'];
    }
}
