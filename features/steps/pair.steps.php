<?php

/**
 * Pair mode steps — runs pair commands via a lightweight shim that
 * instantiates CommandDispatcher directly, bypassing the TTY check.
 */

if (!function_exists('_pair_exec')) {
    /**
     * Runs the pair shim: dispatches each ";"-separated input in one session,
     * optionally interactive with the given answers piped to stdin.
     */
    function _pair_exec(object $world, string $input, ?string $answers = null, bool $greet = false): void
    {
        $interactive = $answers !== null ? 'true' : 'false';

        $shim = _pair_shim_source($interactive, $greet);
        file_put_contents($world->projectDir . '/_pair_shim.php', $shim);

        $cmd = [
            $world->phpBin, '-d', 'xdebug.mode=off',
            $world->projectDir . '/_pair_shim.php',
            $input,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $world->projectDir);

        if ($answers !== null) {
            fwrite($pipes[0], implode("\n", array_map('trim', explode(',', $answers))) . "\n");
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $world->exitCode = proc_close($process);
        $world->output = $stdout . $stderr;
    }

    /**
     * Builds the shim source with the given interactivity flag.
     */
    function _pair_shim_source(string $interactive, bool $greet = false): string
    {
        $shim = <<<'PHP'
    <?php
    require __DIR__ . '/vendor/autoload.php';

    use PhpSpec\CodeGeneration\ClassGenerator;
    use PhpSpec\CodeGeneration\SpecGenerator;
    use PhpSpec\Configuration;
    use PhpSpec\Console\Application;
    use PhpSpec\Console\Command\Pair\CommandDispatcher;
    use PhpSpec\Console\Command\Pair\PairOutput;
    use Symfony\Component\Console\Output\StreamOutput;

    $config = new Configuration(__DIR__);
    $specSuffix = $config->getSpecSuffix();
    $output = new StreamOutput(fopen('php://stdout', 'w'));
    $pairOutput = new PairOutput($output);

    $app = new Application();
    $app->setAutoExit(false);

    $dispatcher = new CommandDispatcher(
        new SpecGenerator(ltrim($config->getSpecPath(), './'), specSuffix: $specSuffix),
        new ClassGenerator(ltrim($config->getSrcPath(), './')),
        $config,
        $pairOutput,
        interactive: %INTERACTIVE%,
        application: $app,
    );

    %GREET%

    foreach (array_filter(array_map('trim', explode(';', $argv[1] ?? ''))) as $command) {
        $dispatcher->dispatch($command);
    }
    PHP;

        return str_replace(
            ['%INTERACTIVE%', '%GREET%'],
            [$interactive, $greet ? '$dispatcher->greet();' : ''],
            $shim,
        );
    }
}

when('I run phpspec pair with input {string}', function (string $input) {
    _pair_exec($this, $input);
});

when('I run phpspec pair with input {string} answering {string}', function (string $input, string $answers) {
    _pair_exec($this, $input, $answers);
});

when('I start a pair session', function () {
    _pair_exec($this, '', null, true);
});
