<?php

use PhpSpec\Console\Command\Run\GenerationCandidates;
use PhpSpec\Console\Command\Run\RunOutcome;
use PhpSpec\Console\Command\Run\SuiteSummary;

describe(RunOutcome::class, function () {

    it('carries the generation candidates and the suite summary', function () {
        $candidates = new GenerationCandidates(missingSpecClasses: ['App\\Calculator']);
        $summary = new SuiteSummary('red');

        $outcome = new RunOutcome($candidates, $summary);

        expect($outcome->candidates)->toBe($candidates);
        expect($outcome->summary)->toBe($summary);
    });

    it('reports empty candidates when there is nothing to generate', function () {
        expect((new RunOutcome(null, null))->isEmptyCandidates())->toBe(true);
        expect((new RunOutcome(new GenerationCandidates()))->isEmptyCandidates())->toBe(true);
        expect((new RunOutcome(new GenerationCandidates(missingSpecClasses: ['App\\Calculator'])))->isEmptyCandidates())->toBe(false);
    });

});
