<?php

use PhpSpec\Report\CountLines;
use PhpSpec\Result\Counts;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;

describe(CountLines::class, function () {

    let('lines', fn() => fn(array $results): array => (new CountLines((new Counts(new SuiteResult($results)))->toArray()))->lines());

    it('counts the stories on one line and the specs on another, each outcome with its tone', function () {
        $noted = new StepResult('Given the old API', 'passed');
        $noted->raised([['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => 'a.php', 'line' => 1]]);
        $failed = new ExampleResult('fails', [MatchResult::failed(1, 2, 'no', __FILE__, __LINE__)]);

        expect(($this->lines)([
            new FeatureResult('Legacy', [new ScenarioResult('Old API', [$noted, new StepResult('Then it waits', 'undefined')])]),
            new SpecificationResult('Basket', [new ExampleResult('works', [MatchResult::passed()]), $failed]),
        ]))->toBe([
            ['heading' => '1 feature, 1 scenario, 2 steps', 'parts' => [
                ['text' => '1 passed', 'tone' => 'passed'],
                ['text' => '1 undefined', 'tone' => 'undefined'],
                ['text' => '1 deprecation', 'tone' => 'warned'],
            ]],
            ['heading' => '1 spec, 2 examples', 'parts' => [
                ['text' => '1 passed', 'tone' => 'passed'],
                ['text' => '1 failed', 'tone' => 'failed'],
            ]],
        ]);
    });

    it('names a spec with no example on its own', function () {
        expect(($this->lines)([new SpecificationResult('Empty', [])]))->toBe([['heading' => '1 spec', 'parts' => []]]);
    });
});
