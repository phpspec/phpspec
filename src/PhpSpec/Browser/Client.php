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

use Closure;

use function PhpSpec\attach;

/**
 * @internal
 * Lightweight HTTP client using file_get_contents + stream contexts (zero dependencies).
 */
final class Client
{
    /** @var ?Closure(string, resource): array{body: string|false, headers: list<string>} */
    private ?Closure $transport;

    /**
     * Creates a new HTTP client.
     *
     * @param string $baseUrl base URL prepended to all request paths
     * @param ?Closure(string, resource): array{body: string|false, headers: list<string>} $transport optional custom transport callable for testing
     */
    public function __construct(
        private readonly string $baseUrl,
        ?Closure $transport = null,
    ) {
        $this->transport = $transport;
    }

    /**
     * Sends an HTTP request and returns a Response.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $path URL path appended to base URL
     * @param array{json?: array<string, mixed>, body?: string, headers?: array<string, string>} $options request options
     *
     * @return Response
     */
    public function request(string $method, string $path, array $options = []): Response
    {
        $headers = $options['headers'] ?? [];
        $content = null;

        if (isset($options['json'])) {
            $content = json_encode($options['json']);
            $headers['Content-Type'] ??= 'application/json';
        } elseif (isset($options['body'])) {
            $content = $options['body'];
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        $contextOptions = [
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headerLines),
                'ignore_errors' => true,
            ],
        ];

        if ($content !== null) {
            $contextOptions['http']['content'] = $content;
        }

        $context = stream_context_create($contextOptions);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        if ($this->transport) {
            ['body' => $body, 'headers' => $rawHeaders] = ($this->transport)($url, $context);
        } else {
            $http_response_header = [];
            set_error_handler(fn() => true);
            try {
                $body = file_get_contents($url, false, $context);
            } finally {
                restore_error_handler();
            }
            $rawHeaders = $http_response_header;
        }

        if ($body === false) {
            $body = '';
        }

        $status = 0;
        $responseHeaders = [];

        if ($rawHeaders !== []) {
            foreach ($rawHeaders as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                    $status = (int) $matches[1];
                } elseif (str_contains($header, ':')) {
                    [$name, $value] = explode(':', $header, 2);
                    $responseHeaders[trim($name)] = trim($value);
                }
            }
        }

        $response = new Response($status, $body, $responseHeaders);

        // What the server said, handed over so a failed expectation about a
        // response is read next to the body that explains it: an assertion on
        // the status says "expected 200, got 500" and never why. Attached on
        // every request and only ever read when something needs attention, so a
        // green suite pays nothing and the last request is the one reported.
        attach('http.request', strtoupper($method) . ' ' . $url . ' (' . $status . ')');
        attach('http.response', $body);

        return $response;
    }
}
