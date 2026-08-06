<?php

use PhpSpec\Coverage\CoverageDriver;
use PhpSpec\Coverage\JsonReportBuilder;
use PhpSpec\Coverage\PerExampleCollector;
use PhpSpec\Filesystem;

describe(JsonReportBuilder::class, function () {

    beforeEach(function (CoverageDriver $driver) {
        allow($driver->stop())->toReturn([
            '/project/src/App/Calculator.php' => [12 => 1, 13 => -1],
            '/project/spec/App/Calculator.spec.php' => [5 => 1],
            '/project/vendor/lib/functions.php' => [3 => 1],
        ]);

        $this->collector = new PerExampleCollector($driver);
        $this->collector->beginSpec('spec/App/Calculator.spec.php');
        $this->collector->pushContext('Calculator');
        $this->collector->beginExample();
        $this->collector->endExample('adds two numbers');
    });

    it('keeps only sources under the src path, relative to the project root, with checksums', function (Filesystem $filesystem) {
        allow($filesystem->read())->toReturnUsing(
            fn(string $path) => $path === '/project/src/App/Calculator.php' ? 'source code' : 'other',
        );
        $builder = new JsonReportBuilder($filesystem);

        $report = $builder->build($this->collector, '/project/src', '/project');

        expect($report['sources'])->toBe([
            'src/App/Calculator.php' => [
                'checksum' => md5('source code'),
                'lines' => [
                    12 => ['spec/App/Calculator.spec.php::Calculator > adds two numbers'],
                ],
                // What there was to run, as against what ran: line 13 was
                // executable and nothing reached it, which is precisely what a
                // reader of the artifact could not tell before.
                'executable' => [12, 13],
            ],
        ]);
    });

    it('handles Windows-style backslash paths', function (CoverageDriver $winDriver, Filesystem $filesystem) {
        allow($winDriver->stop())->toReturn([
            'C:\\project\\src\\App\\Calculator.php' => [12 => 1],
        ]);
        allow($filesystem->read())->toReturn('code');
        $collector = new PerExampleCollector($winDriver);
        $collector->beginSpec('spec\\App\\Calculator.spec.php');
        $collector->beginExample();
        $collector->endExample('adds');
        $builder = new JsonReportBuilder($filesystem);

        $report = $builder->build($collector, 'C:\\project\\src', 'C:\\project');

        expect($report['sources'])->toHaveKey('src/App/Calculator.php');
        expect($report['tests'])->toHaveKey('spec/App/Calculator.spec.php::adds');
    });

    it('adds a checksum of each spec file to the test metadata', function (Filesystem $filesystem) {
        allow($filesystem->read())->toReturnUsing(
            fn(string $path) => $path === 'spec/App/Calculator.spec.php' ? 'spec code' : 'other',
        );
        $builder = new JsonReportBuilder($filesystem);

        $report = $builder->build($this->collector, '/project/src', '/project');

        $test = $report['tests']['spec/App/Calculator.spec.php::Calculator > adds two numbers'];
        expect($test['spec_file'])->toBe('spec/App/Calculator.spec.php');
        expect($test['spec_checksum'])->toBe(md5('spec code'));
        expect($test['time'])->toBeGreaterThan(0);
        expect($test['memory'])->toBeGreaterThan(0);
    });
});
