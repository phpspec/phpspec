<?php

use PhpSpec\Coverage\JsonReport;

describe(JsonReport::class, function () {

    beforeEach(function () {
        $this->filePath = sys_get_temp_dir() . '/phpspec_json_report_' . uniqid() . '/coverage.json';
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
});
