<?php

/**
 * Assertion steps — all Then steps that verify command output,
 * exit codes, and generated files.
 */

// -- Pass / fail assertions --------------------------------------------

then('all examples should pass', function () {
    if ($this->exitCode !== 0) {
        throw new \RuntimeException(
            "Expected exit code 0, got {$this->exitCode}.\nOutput:\n{$this->output}",
        );
    }
});

then('all steps should pass', function () {
    if ($this->exitCode !== 0) {
        throw new \RuntimeException(
            "Expected exit code 0, got {$this->exitCode}.\nOutput:\n{$this->output}",
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
            "Expected exit code {$code}, got {$this->exitCode}.\nOutput:\n{$this->output}",
        );
    }
});

then('the exit code should not be {int}', function (int $code) {
    if ($this->exitCode === $code) {
        throw new \RuntimeException(
            "Expected exit code NOT to be {$code}, got {$this->exitCode}.\nOutput:\n{$this->output}",
        );
    }
});

// -- Output content ----------------------------------------------------

then('the output should contain {string}', function (string $text) {
    if (!str_contains($this->output, $text)) {
        throw new \RuntimeException(
            "Expected output to contain \"{$text}\".\nOutput:\n{$this->output}",
        );
    }
});

then('the output should not contain {string}', function (string $text) {
    if (str_contains($this->output, $text)) {
        throw new \RuntimeException(
            "Expected output NOT to contain \"{$text}\".\nOutput:\n{$this->output}",
        );
    }
});

then('the output should contain {string} exactly {int} times', function (string $text, int $times) {
    $found = substr_count($this->output, $text);
    if ($found !== $times) {
        throw new \RuntimeException(
            "Expected output to contain \"{$text}\" exactly {$times} time(s), found {$found}.\nOutput:\n{$this->output}",
        );
    }
});

then('the file {string} should not contain {string}', function (string $path, string $text) {
    $content = (string) file_get_contents($this->projectDir . '/' . $path);
    if (str_contains($content, $text)) {
        throw new \RuntimeException(
            "Expected \"{$path}\" NOT to contain \"{$text}\".\nContent:\n{$content}",
        );
    }
});

then('the file {string} should contain {string} exactly {int} times', function (string $path, string $text, int $times) {
    $content = (string) file_get_contents($this->projectDir . '/' . $path);
    $found = substr_count($content, $text);
    if ($found !== $times) {
        throw new \RuntimeException(
            "Expected \"{$path}\" to contain \"{$text}\" exactly {$times} time(s), found {$found}.\nContent:\n{$content}",
        );
    }
});

// PhpSpec answers an agent in JSON Lines: one self-contained event per line, as
// it happens. Decoding line by line is the whole of what a consumer does, so the
// harness reads the output exactly the way the contract asks it to be read.
$events = static function (string $output): array {
    $events = [];

    foreach (explode("\n", trim($output)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        try {
            $events[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Expected every line of the output to be a JSON event ({$e->getMessage()}).\nLine:\n{$line}",
            );
        }
    }

    return $events;
};

// The entries a run reports: one per example or scenario that needs attention.
$entries = static function (array $events): array {
    return array_values(array_filter($events, fn(array $event) => ($event['event'] ?? null) === 'example'));
};

then('the output should be valid JSON', function () use ($events) {
    $events($this->output);
});

then('the output should have {int} events', function (int $count) use ($events) {
    expect($events($this->output))->toHaveLength($count);
});

// The stream's shape: an agent knows where the run starts and that the summary
// is the last word, whatever happened in between.
then('the first event should be {string}', function (string $name) use ($events) {
    $stream = $events($this->output);

    expect($stream[0]['event'] ?? null)->toBe($name);
});

then('the last event should be {string}', function (string $name) use ($events) {
    $stream = $events($this->output);

    expect(end($stream)['event'] ?? null)->toBe($name);
});

// How many things the run says are worth acting on: one per example or scenario,
// never one per step of the same broken scenario.
then('the report should have {int} entries', function (int $count) use ($events, $entries) {
    expect($entries($events($this->output)))->toHaveLength($count);
});

then('the report should have {int} entry', function (int $count) use ($events, $entries) {
    expect($entries($events($this->output)))->toHaveLength($count);
});

// One field answers "what went wrong", whatever the entry's state: a consumer
// should not have to know that an error hides its text somewhere else.
then('every reported entry should carry a message', function () use ($events, $entries) {
    $reported = $entries($events($this->output));

    expect($reported)->not()->toBe([]);

    foreach ($reported as $entry) {
        expect($entry)->toHaveKey('message');
    }
});

// A step's failure is data, not a sentence to parse back: the values that did
// not match ride on the step that did not pass.
then('the failing step should report expected {int} and actual {int}', function (int $expected, int $actual) use ($events, $entries) {
    $entry = $entries($events($this->output))[0] ?? [];
    $failing = array_values(array_filter($entry['steps'] ?? [], fn(array $step) => $step['state'] === 'failing'));

    expect($failing)->not()->toBe([]);
    expect($failing[0]['expectation']['expected'])->toBe($expected);
    expect($failing[0]['expectation']['actual'])->toBe($actual);
});

// Both sides of a failure, plainly named and compared as the JSON a reader
// decodes, so "true" and "[]" mean what they say.
then('the reported entry should expect {string} and have got {string}', function (string $expected, string $actual) use ($events, $entries) {
    $expectation = $entries($events($this->output))[0]['expectation'] ?? [];

    expect(json_encode($expectation['expected'] ?? null))->toBe($expected);
    expect(json_encode($expectation['actual'] ?? null))->toBe($actual);
});

// Context only the test could reach, handed over under a name and read while
// the run still stood where it was attached.
then('the reported entry should have attached {string} containing {string}', function (string $name, string $text) use ($events, $entries) {
    $attached = $entries($events($this->output))[0]['attachments'][$name] ?? null;

    expect(is_array($attached) ? ($attached['value'] ?? $attached['error'] ?? '') : (string) $attached)->toContain($text);
});

// What the subject printed is a diagnosis about the entry it printed under, and
// it reaches the reader as data instead of landing in the middle of the report.
then('the reported entry should have printed {string}', function (string $text) use ($events, $entries) {
    $entry = $entries($events($this->output))[0] ?? [];
    $printed = $entry['output'] ?? null;

    expect(is_array($printed) ? ($printed['value'] ?? '') : (string) $printed)->toContain($text);
});

// Every reported entry must be addressable on its own: an id that two entries
// share cannot answer "is THIS failure still here?".
then('the failing entries should have distinct ids', function () use ($events, $entries) {
    $ids = array_column($entries($events($this->output)), 'id');

    expect(count($ids))->toBeGreaterThan(1);
    expect(array_unique($ids))->toHaveLength(count($ids));
});

// Standard output on its own, for the runs where the error stream carries text
// of its own (PHP's fatal report) and the document must still stand alone.
then('the standard output should be valid JSON', function () use ($events) {
    $events($this->stdout);
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

then('the file {string} should contain {string}', function (string $path, string $text) {
    $full = $this->projectDir . '/' . $path;
    if (!file_exists($full)) {
        throw new \RuntimeException("File not found: $full");
    }
    $content = (string) file_get_contents($full);
    if (!str_contains($content, $text)) {
        throw new \RuntimeException(
            "Expected file \"$path\" to contain \"$text\".\nActual content:\n$content",
        );
    }
});

// A mutant is a change to one method, so a mutation testing tool asks the
// report which lines that method occupies before it asks who covered them.
then('the file {string} should record the method {string} spanning lines {int} to {int}', function (string $path, string $method, int $start, int $end) {
    $report = json_decode((string) file_get_contents($this->projectDir . '/' . $path), true, 512, JSON_THROW_ON_ERROR);

    $methods = [];
    foreach ($report['sources'] as $source) {
        $methods += $source['methods'] ?? [];
    }

    expect($methods)->toHaveKey($method);
    expect($methods[$method])->toBe(['start' => $start, 'end' => $end]);
});

then('the file {string} should contain:', function (string $path, string $text) {
    $content = file_get_contents($this->projectDir . '/' . $path);
    expect($content)->toContain($text);
});

then('the file {string} should not contain:', function (string $path, string $text) {
    $content = file_get_contents($this->projectDir . '/' . $path);
    expect($content)->not()->toContain($text);
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

then('no file {string} should be generated', function (string $path) {
    expect(file_exists($this->projectDir . '/' . $path))->toBeFalse();
});
