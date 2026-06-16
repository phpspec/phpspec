<?php

/**
 * Browser steps — starts a PHP built-in server for testing
 * visit()/get()/post()/put()/patch()/delete() DSL functions.
 */

// Static PID tracker — survives across scenarios (StepWorld is fresh each time)
$_browserServerPid = null;

// Kill the last server when the process exits (prevents orphaned servers)
register_shutdown_function(function () use (&$_browserServerPid) {
    if ($_browserServerPid !== null) {
        @posix_kill($_browserServerPid, SIGTERM);
        $_browserServerPid = null;
    }
});

given('any previous HTTP server is stopped', function () use (&$_browserServerPid) {
    if ($_browserServerPid !== null) {
        @posix_kill($_browserServerPid, SIGTERM);
        usleep(50_000); // let it die
        $_browserServerPid = null;
    }
});

given('a local HTTP server with router:', function (string $router) use (&$_browserServerPid) {
    // Write router file
    $routerPath = $this->projectDir . '/router.php';
    file_put_contents($routerPath, $router);

    // Find a free port
    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    socket_bind($sock, '127.0.0.1', 0);
    socket_getsockname($sock, $addr, $port);
    socket_close($sock);

    $this->serverPort = $port;

    // Start PHP built-in server in background
    $cmd = sprintf(
        '%s -S 127.0.0.1:%d -t %s %s > /dev/null 2>&1 & echo $!',
        escapeshellarg($this->phpBin),
        $port,
        escapeshellarg($this->projectDir),
        escapeshellarg($routerPath),
    );
    $pid = (int) trim(shell_exec($cmd));
    $this->serverPid = $pid;
    $_browserServerPid = $pid;

    // Wait for server to accept connections (up to 3s)
    $start = microtime(true);
    while (microtime(true) - $start < 3) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($fp) {
            fclose($fp);
            break;
        }
        usleep(50_000);
    }

    // Update phpspec.json with base_url
    $configPath = $this->projectDir . '/phpspec.json';
    $config = json_decode(file_get_contents($configPath), true) ?? [];
    $config['base_url'] = "http://127.0.0.1:{$port}";
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
});
