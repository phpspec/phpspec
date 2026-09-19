<?php

/**
 * Browser steps — starts a PHP built-in server for testing
 * visit()/get()/post()/put()/patch()/delete() DSL functions.
 */

use PhpSpec\Acceptance\Support\File;
use PhpSpec\Acceptance\Support\Server;

// The world is fresh each scenario, so the running server also parks here for
// the next scenario (and process shutdown) to stop.
$_browserServer = null;

register_shutdown_function(function () use (&$_browserServer) {
    $_browserServer?->stop();
});

given('any previous HTTP server is stopped', function () use (&$_browserServer) {
    $_browserServer?->stop();
    $_browserServer = null;
});

given('a local HTTP server responding with:', function (string $script) use (&$_browserServer) {
    $scriptPath = $this->projectDir . '/server.php';
    (new File($scriptPath))->write($script);

    $url = 'http://127.0.0.1:' . Server::findFreePort();
    $server = new Server($url, $this->projectDir, $scriptPath);
    $server->start();
    $server->wait(3);

    $this->server = $server;
    $_browserServer = $server;

    $config = new File($this->projectDir . '/phpspec.json');
    $settings = json_decode($config->read(), true) ?? [];
    $settings['base_url'] = $url;
    $config->write((string) json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
});
