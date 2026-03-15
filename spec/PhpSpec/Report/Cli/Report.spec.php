<?php

use PhpSpec\Report\Cli\Report;
use PhpSpec\Report\Formatter;
use PhpSpec\Result\SuiteResult;

describe(Report::class, function () {

    it('delegates print to the formatter', function (Formatter $formatter) {
        $results = new SuiteResult([]);

        $report = new Report();
        $report->setFormatter($formatter);
        $report->print($results);

        expect($formatter->format($results))->toBeCalled();
    });

});
