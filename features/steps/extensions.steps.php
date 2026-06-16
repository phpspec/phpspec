<?php

/**
 * Extension steps — steps for testing the extension system.
 */

then('the file {string} should contain {string}', function (string $path, string $expected) {
    $full = $this->projectDir . '/' . $path;
    if (!file_exists($full)) {
        throw new \RuntimeException("File not found: $full");
    }
    $content = file_get_contents($full);
    if (!str_contains($content, $expected)) {
        throw new \RuntimeException(
            "Expected file \"$path\" to contain \"$expected\".\nActual content:\n$content"
        );
    }
});

when('I run phpspec command {string}', function (string $command) {
    _phpspec_exec($this, $command);
});
