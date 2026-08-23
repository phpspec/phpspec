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

use RuntimeException;

/**
 * A PHP built-in server for acceptance scenarios, listening once wait() returns.
 */
final class Server
{
    /** @var resource|null */
    private $process = null;

    /** @var resource|null */
    private $stderr = null;

    private string $announced = '';

    public function __construct(
        private readonly string $url,
        private readonly string $docs,
        private readonly string $script,
    ) {}

    public static function findFreePort(): int
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new RuntimeException('Could not create a socket to find a free port.');
        }
        socket_bind($socket, '127.0.0.1', 0);
        socket_getsockname($socket, $address, $port);
        socket_close($socket);

        return $port;
    }

    public function start(): void
    {
        $address = (string) parse_url($this->url, PHP_URL_HOST) . ':' . (int) parse_url($this->url, PHP_URL_PORT);

        $process = proc_open(
            [PHP_BINARY, '-S', $address, '-t', $this->docs, $this->script],
            [2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Could not start the HTTP server on %s.', $address));
        }

        stream_set_blocking($pipes[2], false);
        $this->process = $process;
        $this->stderr = $pipes[2];
    }

    /**
     * Returns once the server has announced it is listening, so the first
     * request can never race the bind.
     */
    public function wait(float $timeout = 3.0): void
    {
        if ($this->stderr === null) {
            throw new RuntimeException('The server was never started: call start() first.');
        }

        $deadline = microtime(true) + $timeout;
        while (microtime(true) < $deadline) {
            $this->announced .= (string) stream_get_contents($this->stderr);
            if (str_contains($this->announced, 'started')) {
                return;
            }
            usleep(10_000);
        }

        throw new RuntimeException(sprintf(
            'The HTTP server did not start within %.1fs. It said: %s',
            $timeout,
            trim($this->announced) === '' ? '(nothing)' : trim($this->announced),
        ));
    }

    public function stop(): void
    {
        if ($this->stderr !== null) {
            fclose($this->stderr);
            $this->stderr = null;
        }
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function url(): string
    {
        return $this->url;
    }
}
