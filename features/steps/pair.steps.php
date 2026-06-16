<?php

/**
 * Pair mode steps — runs pair commands via a lightweight shim that
 * instantiates CommandDispatcher directly, bypassing the TTY check.
 */

when('I run phpspec pair with input {string}', function (string $input) {
    // Write a shim script that exercises CommandDispatcher directly
    $shim = <<<'PHP'
    <?php
    require __DIR__ . '/vendor/autoload.php';

    use PhpSpec\CodeGeneration\ClassGenerator;
    use PhpSpec\CodeGeneration\SpecGenerator;
    use PhpSpec\Configuration;
    use PhpSpec\Console\Application;
    use PhpSpec\Console\Command\Pair\CommandDispatcher;
    use PhpSpec\Console\Command\Pair\PairOutput;
    use PhpSpec\Loader;
    use PhpSpec\Runner;
    use Symfony\Component\Console\Output\StreamOutput;

    $config = new Configuration(__DIR__);
    $specSuffix = $config->getSpecSuffix();
    $output = new StreamOutput(fopen('php://stdout', 'w'));
    $pairOutput = new PairOutput($output);

    $app = new Application();
    $app->setAutoExit(false);

    $dispatcher = new CommandDispatcher(
        new Loader(specSuffix: $specSuffix),
        new Runner(),
        new SpecGenerator(ltrim($config->getSpecPath(), './'), specSuffix: $specSuffix),
        new ClassGenerator(ltrim($config->getSrcPath(), './')),
        $config,
        $pairOutput,
        interactive: false,
        application: $app,
    );

    $input = $argv[1] ?? '';
    $dispatcher->dispatch($input);
    PHP;

    file_put_contents($this->projectDir . '/_pair_shim.php', $shim);

    $cmd = [
        $this->phpBin, '-d', 'xdebug.mode=off',
        $this->projectDir . '/_pair_shim.php',
        $input,
    ];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptors, $pipes, $this->projectDir);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $this->exitCode = proc_close($process);
    $this->output = $stdout . $stderr;
});
