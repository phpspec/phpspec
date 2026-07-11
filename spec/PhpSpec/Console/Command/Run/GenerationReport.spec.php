<?php

use PhpSpec\Console\Command\Run\GenerationCandidates;
use PhpSpec\Console\Command\Run\GenerationReport;

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

    it('round-trips candidates through write and read', function () {
        $candidates = new GenerationCandidates(missingSpecClasses: ['App\\Foo']);

        GenerationReport::write($this->path, $candidates);

        expect(GenerationReport::read($this->path)->missingSpecClasses)->toBe(['App\\Foo']);
    });

    it('reads null when the report file is missing', function () {
        expect(GenerationReport::read($this->path))->toBeNull();
    });

    it('reads null from an empty report file', function () {
        file_put_contents($this->path, '');

        expect(GenerationReport::read($this->path))->toBeNull();
    });
});
