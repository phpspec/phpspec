<?php

use PhpSpec\Coverage\JsonReport;
use RuntimeException;

describe(JsonReport::class, function () {

    beforeEach(function () {
        $this->filePath = sys_get_temp_dir() . '/phpspec_json_report_' . bin2hex(random_bytes(8)) . '/coverage.json';
        $this->tests = [
            'spec/App/Calculator.spec.php::Calculator > adds two numbers' => [
                'time' => 0.0021,
                'memory' => 524288,
                'spec_file' => 'spec/App/Calculator.spec.php',
                'spec_checksum' => 'abc123',
            ],
        ];
        $this->sources = [
            'src/App/Calculator.php' => [
                'checksum' => 'def456',
                'lines' => [
                    12 => ['spec/App/Calculator.spec.php::Calculator > adds two numbers'],
                ],
            ],
        ];
    });

    afterEach(function () {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
            rmdir(dirname($this->filePath));
        }
    });

    it('writes the report to the given file path, creating parent directories', function () {
        (new JsonReport())->render($this->tests, $this->sources, $this->filePath);

        expect(file_exists($this->filePath))->toBeTrue();
    });

    it('renders a version 1 document with tests and sources sections', function () {
        (new JsonReport())->render($this->tests, $this->sources, $this->filePath);

        $document = json_decode((string) file_get_contents($this->filePath), true);
        expect($document['version'])->toBe(1);
        expect($document['tests'])->toBe($this->tests);
        expect($document['sources'])->toBe($this->sources);
    });

    it('writes the document a whole encoding would, byte for byte, a source file at a time', function () {
        $tests = [
            'spec/App/Ünïcode.spec.php::Ünïcode > reads "quotes" and slashes/' => ['time' => 0.1, 'memory' => 2097152, 'spec_file' => 'spec/App/Ünïcode.spec.php', 'spec_checksum' => 'a1'],
            'spec/App/Calculator.spec.php::Calculator > adds' => ['time' => 1.0E-5, 'memory' => 524288, 'spec_file' => 'spec/App/Calculator.spec.php', 'spec_checksum' => 'b2'],
        ];
        $sources = [
            'src/App/Calculator.php' => [
                'checksum' => 'def456',
                'lines' => [12 => ['spec/App/Calculator.spec.php::Calculator > adds'], 14 => []],
                'executable' => [12, 13, 14],
                'methods' => ['add' => ['start' => 10, 'end' => 15]],
            ],
            'src/App/Empty.php' => ['checksum' => 'e5', 'lines' => []],
        ];

        foreach ([[$tests, $sources], [[], []], [$tests, []]] as [$someTests, $someSources]) {
            (new JsonReport())->render($someTests, $someSources, $this->filePath);

            expect(file_get_contents($this->filePath))->toBe(json_encode(['version' => 1, 'tests' => $someTests, 'sources' => $someSources], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    });

    it('takes the place of the report it replaces, keeping its mode, and leaves nothing beside it', function () {
        (new JsonReport())->render($this->tests, $this->sources, $this->filePath);
        chmod($this->filePath, 0640);

        (new JsonReport())->render($this->tests, [], $this->filePath);

        expect(fileperms($this->filePath) & 0777)->toBe(0640);
        expect(json_decode((string) file_get_contents($this->filePath), true)['sources'])->toBe([]);
        expect(scandir(dirname($this->filePath)))->toBe(['.', '..', 'coverage.json']);
    });

    it('leaves nothing behind when the document cannot be encoded', function () {
        expect(fn() => (new JsonReport())->render(['spec/Bad.spec.php::Bad' => ['time' => 0.1, 'memory' => 1, 'spec_file' => "\xB1\x31", 'spec_checksum' => 'c3']], [], $this->filePath))->toThrow(RuntimeException::class);
        expect(scandir(dirname($this->filePath)))->toBe(['.', '..']);

        rmdir(dirname($this->filePath));
    });
});
