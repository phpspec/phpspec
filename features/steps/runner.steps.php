<?php

/**
 * Runner steps — all When steps that execute phpspec commands
 * in the temporary project directory.
 */

if (!function_exists('_phpspec_exec')) {
    function _phpspec_exec(object $world, string $args, bool $interactive = false): void
    {
        if (!$interactive) {
            try {
                $result = \PhpSpec\InProcessRunner::run($world->projectDir, $args);
                $world->exitCode = $result->exitCode;
                $world->output = $result->output;
                return;
            } catch (\PhpSpec\ClassConflictException) {
                // Class already loaded from a different project — fall back to subprocess
            } catch (\Error $e) {
                if (!str_contains($e->getMessage(), 'Cannot declare class')
                    && !str_contains($e->getMessage(), 'Cannot redeclare class')
                    && !str_contains($e->getMessage(), 'Cannot declare interface')
                    && !str_contains($e->getMessage(), 'Cannot redeclare function')) {
                    throw $e;
                }
                // Class/interface redefinition — fall back to subprocess
            }
        }

        _phpspec_exec_subprocess($world, $args, $interactive);
    }

    function _phpspec_exec_subprocess(object $world, string $args, bool $interactive = false): void
    {
        $cmd = [$world->phpBin, '-d', 'xdebug.mode=off', $world->phpspecBin, ...preg_split('/\s+/', $args)];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $world->projectDir);

        if ($interactive) {
            fwrite($pipes[0], str_repeat("y\n", 30));
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $world->exitCode = proc_close($process);
        $world->output = $stdout . $stderr;
    }
}

// -- Basic run commands ------------------------------------------------

when('I run phpspec run', function () {
    _phpspec_exec($this, 'run');
});

when('I run the spec', function () {
    _phpspec_exec($this, 'run');
});

when('I run phpspec run with option {string}', function (string $options) {
    _phpspec_exec($this, 'run ' . $options);
});

when('I run phpspec run {string}', function (string $path) {
    _phpspec_exec($this, 'run ' . $path);
});

// -- Describe command --------------------------------------------------

when('I run phpspec describe {string}', function (string $class) {
    _phpspec_exec($this, 'describe ' . $class);
    $specPath = str_replace('\\', '/', $class);
    $this->lastFile = $this->projectDir . '/spec/' . $specPath . '.spec.php';
});

when('I run phpspec describe {string} with option {string}', function (string $class, string $options) {
    _phpspec_exec($this, 'describe ' . $class . ' ' . $options);
    $specPath = str_replace('\\', '/', $class);
    $this->lastFile = $this->projectDir . '/spec/' . $specPath . '.spec.php';
});

// -- Exemplify command -------------------------------------------------

when('I run phpspec exemplify {string} {string}', function (string $class, string $method) {
    _phpspec_exec($this, 'exemplify ' . $class . ' ' . $method);
    $specPath = str_replace('\\', '/', $class);
    $this->lastFile = $this->projectDir . '/spec/' . $specPath . '.spec.php';
});

// -- Interactive run (auto-answer "y" to prompts) ----------------------

when('I run phpspec run and answer {string} to generation prompts', function (string $_answer) {
    _phpspec_exec($this, 'run', interactive: true);
});

when('I run phpspec run with option {string} and answer {string} to generation prompts', function (string $options, string $_answer) {
    _phpspec_exec($this, 'run ' . $options, interactive: true);
});

when('I run phpspec run {string} and answer {string} to generation prompts', function (string $path, string $_answer) {
    _phpspec_exec($this, 'run ' . $path, interactive: true);
});

// -- Implementation steps (bdd-workflow) --------------------------------

when('I implement the greet method to return {string}', function (string $_value) {
    $file = $this->projectDir . '/src/App/Greeter.php';
    file_put_contents($file, <<<'PHP'
    <?php
    namespace App;

    class Greeter {
        public function greet(string $name): string {
            return "Hello, $name!";
        }
    }
    PHP);
});

when('I implement the Greeter class', function () {
    $file = $this->projectDir . '/src/App/Greeter.php';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($file, <<<'PHP'
    <?php
    namespace App;

    class Greeter {
        public function greet(string $name): string {
            return "Hello, $name!";
        }
    }
    PHP);
});
