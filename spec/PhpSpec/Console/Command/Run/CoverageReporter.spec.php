<?php

use PhpSpec\Console\Command\Run\CoverageReporter;
use PhpSpec\Coverage\CoverageCollector;
use Symfony\Component\Console\Output\BufferedOutput;

describe(CoverageReporter::class, function () {

    context('start', function () {

        if (CoverageCollector::isAvailable()) {

            it('returns true when Xdebug coverage is available', function () {
                $output = new BufferedOutput();
                $reporter = new CoverageReporter();
                expect($reporter->start($output))->toBeTrue();
            });

        } else {

            it('returns false when Xdebug coverage is not available', function () {
                $output = new BufferedOutput();
                $reporter = new CoverageReporter();
                expect($reporter->start($output))->toBeFalse();
            });

            it('writes error message when Xdebug coverage is not available', function () {
                $output = new BufferedOutput();
                $reporter = new CoverageReporter();
                $reporter->start($output);
                expect($output->fetch())->toContain('Code coverage requires Xdebug');
            });

        }

    });
});
