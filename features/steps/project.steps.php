<?php

/**
 * Project setup steps — beforeScenario hook and all Given steps
 * for creating temporary project structures.
 */

beforeScenario(function () {
    // Clean up stale temp dirs (older than 1 hour)
    foreach (glob(sys_get_temp_dir() . '/phpspec_feat_*') as $dir) {
        if (is_dir($dir) && filemtime($dir) < time() - 3600) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    $this->projectDir = sys_get_temp_dir() . '/phpspec_feat_' . uniqid();
    mkdir($this->projectDir, 0777, true);
    mkdir($this->projectDir . '/spec', 0777, true);
    mkdir($this->projectDir . '/src', 0777, true);

    // Symlink vendor for PhpSpec class + function autoloading
    symlink(realpath(__DIR__ . '/../../vendor'), $this->projectDir . '/vendor');

    // Default phpspec.json with App\ autoloading
    file_put_contents($this->projectDir . '/phpspec.json', json_encode([
        'autoload' => ['App\\' => 'src/App', 'Acme\\' => 'lib/Acme'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $this->output = '';
    $this->exitCode = 0;
    $this->lastFile = null;
    $this->phpBin = PHP_BINARY;
    $this->phpspecBin = realpath(__DIR__ . '/../../bin/phpspec');
});

// -- Project structure -------------------------------------------------

given('a PSR-4 project with "spec" and "src" directories', function () {
    // Already created in beforeScenario
});

given('a PSR-4 project with "spec", "src", and "features" directories', function () {
    if (!is_dir($this->projectDir . '/features/steps')) {
        mkdir($this->projectDir . '/features/steps', 0777, true);
    }
});

given('a PSR-4 project with Composer autoloading', function () {
    // vendor/ already symlinked in beforeScenario
});

// -- File writing steps ------------------------------------------------
// All these receive a file path ({string}) and content (doc string).

given('a spec file {string}:', function (string $path, string $content) {
    $full = $this->projectDir . '/' . $path;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($full, $content);
    $this->lastFile = $full;
});

given('a class {string}:', function (string $path, string $content) {
    $full = $this->projectDir . '/' . $path;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($full, $content);
    $this->lastFile = $full;
});

given('an interface {string}:', function (string $path, string $content) {
    $full = $this->projectDir . '/' . $path;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($full, $content);
    $this->lastFile = $full;
});

given('a file {string}:', function (string $path, string $content) {
    $full = $this->projectDir . '/' . $path;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($full, $content);
});

given('a feature file {string}:', function (string $path, string $content) {
    $full = $this->projectDir . '/' . $path;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($full, $content);
});

given('a step file {string}:', function (string $path, string $content) {
    $full = $this->projectDir . '/' . $path;
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($full, $content);
});

// -- Inline spec wrapper -----------------------------------------------
// Wraps a doc string body inside describe()/it() for simple matcher tests.

given('a spec with example:', function (string $body) {
    $indented = str_replace("\n", "\n        ", trim($body));
    $content = <<<PHP
    <?php
    describe('Test', function () {
        it('works', function () {
            {$indented}
        });
    });
    PHP;
    $specPath = $this->projectDir . '/spec/Test.spec.php';
    $dir = dirname($specPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($specPath, $content);
});

// -- Configuration -----------------------------------------------------

given('a phpspec.json config:', function (string $json) {
    file_put_contents($this->projectDir . '/phpspec.json', $json);
});

given('a phpspec.yaml config:', function (string $yaml) {
    file_put_contents($this->projectDir . '/phpspec.yaml', $yaml);
});

given('no phpspec.json config', function () {
    $jsonPath = $this->projectDir . '/phpspec.json';
    if (file_exists($jsonPath)) {
        unlink($jsonPath);
    }
});
