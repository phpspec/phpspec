<?php

/**
 * Assertion steps — all Then steps that verify command output,
 * exit codes, and generated files.
 */

// -- Pass / fail assertions --------------------------------------------

then('all examples should pass', function () {
    if ($this->exitCode !== 0) {
        throw new \RuntimeException(
            "Expected exit code 0, got {$this->exitCode}.\nOutput:\n{$this->output}"
        );
    }
});

then('all steps should pass', function () {
    if ($this->exitCode !== 0) {
        throw new \RuntimeException(
            "Expected exit code 0, got {$this->exitCode}.\nOutput:\n{$this->output}"
        );
    }
});

then('{int} example should fail', function (int $count) {
    expect($this->output)->toMatch("/{$count} fail/i");
});

// -- Exit code ---------------------------------------------------------

then('the exit code should be {int}', function (int $code) {
    if ($this->exitCode !== $code) {
        throw new \RuntimeException(
            "Expected exit code {$code}, got {$this->exitCode}.\nOutput:\n{$this->output}"
        );
    }
});

then('the exit code should not be {int}', function (int $code) {
    if ($this->exitCode === $code) {
        throw new \RuntimeException(
            "Expected exit code NOT to be {$code}, got {$this->exitCode}.\nOutput:\n{$this->output}"
        );
    }
});

// -- Output content ----------------------------------------------------

then('the output should contain {string}', function (string $text) {
    if (!str_contains($this->output, $text)) {
        throw new \RuntimeException(
            "Expected output to contain \"{$text}\".\nOutput:\n{$this->output}"
        );
    }
});

then('the output should not contain {string}', function (string $text) {
    if (str_contains($this->output, $text)) {
        throw new \RuntimeException(
            "Expected output NOT to contain \"{$text}\".\nOutput:\n{$this->output}"
        );
    }
});

// -- File existence (sets $this->lastFile for chained assertions) ------

then('a spec file {string} should be generated', function (string $path) {
    $full = $this->projectDir . '/' . $path;
    expect(file_exists($full))->toBeTrue();
    $this->lastFile = $full;
});

then('a class file {string} should be generated', function (string $path) {
    $full = $this->projectDir . '/' . $path;
    expect(file_exists($full))->toBeTrue();
    $this->lastFile = $full;
});

then('a file {string} should be generated', function (string $path) {
    $full = $this->projectDir . '/' . $path;
    expect(file_exists($full))->toBeTrue();
    $this->lastFile = $full;
});

then('a class {string} should be generated', function (string $path) {
    $full = $this->projectDir . '/' . $path;
    expect(file_exists($full))->toBeTrue();
    $this->lastFile = $full;
});

// -- File content assertions (use $this->lastFile) ---------------------

then('it should contain a describe block for {string}', function (string $class) {
    $content = file_get_contents($this->lastFile);
    expect($content)->toContain('describe(');
    $shortName = basename(str_replace('\\', '/', $class));
    expect($content)->toContain($shortName);
});

then('it should contain an {string} example', function (string $name) {
    $content = file_get_contents($this->lastFile);
    expect($content)->toContain($name);
});

then('it should contain {string}', function (string $text) {
    $content = file_get_contents($this->lastFile);
    expect($content)->toContain($text);
});

then('the spec file should contain an example for {string}', function (string $method) {
    $content = file_get_contents($this->lastFile);
    expect($content)->toContain($method);
});

then('the class {string} should contain {string}', function (string $path, string $text) {
    $content = file_get_contents($this->projectDir . '/' . $path);
    expect($content)->toContain($text);
});

then('the class should have a {string} method stub', function (string $method) {
    $content = file_get_contents($this->lastFile);
    expect($content)->toContain("function {$method}");
});

// -- Compound assertions -----------------------------------------------

then('a spec and class for {string} should be generated', function (string $fqcn) {
    $relative = str_replace('\\', '/', $fqcn);
    $classPath = $this->projectDir . '/src/' . $relative . '.php';
    expect(file_exists($classPath))->toBeTrue();
    $this->lastFile = $classPath;
});

then('a step file should be generated with step definitions', function () {
    $stepsDir = $this->projectDir . '/features/steps';
    $found = false;
    if (is_dir($stepsDir)) {
        foreach (scandir($stepsDir) as $file) {
            if (str_ends_with($file, '.steps.php')) {
                $content = file_get_contents($stepsDir . '/' . $file);
                if (str_contains($content, 'given(') || str_contains($content, 'when(') || str_contains($content, 'then(')) {
                    $found = true;
                    break;
                }
            }
        }
    }
    expect($found)->toBeTrue();
});
