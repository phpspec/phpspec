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

use PhpSpec\Browser\BrowserRegistry;
use PhpSpec\Browser\Response;

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
