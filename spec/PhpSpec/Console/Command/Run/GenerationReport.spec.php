<?php

use PhpSpec\Console\Command\Run\GenerationCandidates;
use PhpSpec\Console\Command\Run\GenerationReport;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;

describe(GenerationReport::class, function () {

    beforeEach(function () {
        $this->path = sys_get_temp_dir() . '/phpspec_report_' . uniqid() . '.json';
        register_shutdown_function(fn() => @unlink($this->path));
        putenv(GenerationReport::ENV_VAR);
    });

    it('has no requested path for a normal run', function () {
        expect(GenerationReport::requestedPath())->toBeNull();
    });

    it('exposes the requested path from the environment', function () {
        putenv(GenerationReport::ENV_VAR . '=' . $this->path);

        expect(GenerationReport::requestedPath())->toBe($this->path);

        putenv(GenerationReport::ENV_VAR);
    });

    it('treats an empty environment value as no request', function () {
        putenv(GenerationReport::ENV_VAR . '=');

        expect(GenerationReport::requestedPath())->toBeNull();

        putenv(GenerationReport::ENV_VAR);
    });

    it('round-trips a run outcome (candidates and suite summary) through write and read', function () {
        $outcome = new RunOutcome(
            new GenerationCandidates(missingSpecClasses: ['App\\Foo']),
            new SuiteSummary(
                'red',
                ['examples' => 1, 'passes' => 0, 'failures' => 1, 'errors' => 0, 'pending' => 0],
                [['subject' => 'App\\Foo', 'example' => 'works']],
            ),
        );

        GenerationReport::write($this->path, $outcome);

        $read = GenerationReport::read($this->path);
        expect($read->candidates->missingSpecClasses)->toBe(['App\\Foo']);
        expect($read->summary->status())->toBe('red');
        expect($read->summary->failing())->toBe([['subject' => 'App\\Foo', 'example' => 'works']]);
    });

    it('reads a legacy bare-candidates report as an outcome without a summary', function () {
        file_put_contents(
            $this->path,
            (string) json_encode((new GenerationCandidates(missingSpecClasses: ['App\\Legacy']))->toArray()),
        );

        $read = GenerationReport::read($this->path);
        expect($read->candidates->missingSpecClasses)->toBe(['App\\Legacy']);
        expect($read->summary)->toBeNull();
    });

    it('reads null when the report file is missing', function () {
        expect(GenerationReport::read($this->path))->toBeNull();
    });

    it('reads null from an empty report file', function () {
        file_put_contents($this->path, '');

        expect(GenerationReport::read($this->path))->toBeNull();
    });
});
