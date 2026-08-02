<?php

use PhpSpec\Coverage\CoverageVerdict;
use PhpSpec\Report\Formatter\Agent;
use PhpSpec\Report\Formatter\Agent\Fatal;
use PhpSpec\Report\Formatter\Agent\ProcessEnd;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Specification\ExampleError;
use Symfony\Component\Console\Output\BufferedOutput;

// A process end the spec fires by hand, in place of PHP's own shutdown.
class AgentSpecProcessEnd implements ProcessEnd
{
    private ?Closure $ending = null;

    public function atEnd(Closure $ending): void
    {
        $this->ending = $ending;
    }

    public function arrives(?Fatal $fatal = null): void
    {
        ($this->ending)($fatal);
    }
}

describe(Agent::class, function () {

    $render = function (SuiteResult $suite): array {
        $output = new BufferedOutput();
        (new Agent($output))->format($suite);

        return json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);
    };

    it('emits one JSON object with suite, examples and result sections', function () use ($render) {
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        expect($doc)->toHaveKey('suite');
        expect($doc)->toHaveKey('examples');
        expect($doc)->toHaveKey('result');
        expect($doc['suite'])->toBe(['v' => 1, 'event' => 'run_started', 'suite' => 'default', 'examples' => 1, 'steps' => 0, 'seed' => null]);
    });

    it('reports the seed and suite it was told about, so a flaky order is reproducible', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->describeRun(424242, 'spec,features/');
        $formatter->format(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        expect($doc['suite']['seed'])->toBe(424242);
        expect($doc['suite']['suite'])->toBe('spec,features/');
    });

    it('writes the document once, however often the command publishes it', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->format(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        $formatter->publish();
        $formatter->publish();

        expect(substr_count($output->fetch(), '"run_started"'))->toBe(1);
    });

    it('publishes what it collected even when the run never reached its end', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [
            new ExampleResult('holds products', [MatchResult::failed(1, 2, 'Expected 1 to be 2', getcwd() . '/spec/App/Basket.spec.php', 9, null, 'toBe')]),
        ]));

        // No end(): a fatal took the process before the last example.
        $formatter->publish();
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        expect($doc['result']['failing'])->toBe(1);
        expect($doc['result']['duration_ms'])->toBe(0);
        expect($doc['examples'][0]['example'])->toBe('App\\Basket holds products');
    });

    it('carries a missed coverage gate in the result, and counts it as work left', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]));
        $formatter->end(new SuiteResult([]));
        $formatter->covered(new CoverageVerdict(24.34, 90.0));
        $formatter->publish();
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        // JSON has one number type, so 90.0 comes back as 90.
        expect($doc['result']['coverage']['percent'])->toBe(24.3);
        expect((float) $doc['result']['coverage']['required'])->toBe(90.0);
        expect($doc['result']['coverage']['met'])->toBeFalse();
        expect($doc['result']['failing'])->toBe(0);
        expect($doc['result']['actionable'])->toBe(1);
    });

    it('reports coverage without a threshold as met, adding no work', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->end(new SuiteResult([]));
        $formatter->covered(new CoverageVerdict(72.5));
        $formatter->publish();
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        expect($doc['result']['coverage'])->toBe(['percent' => 72.5, 'required' => null, 'met' => true]);
        expect($doc['result']['actionable'])->toBe(0);
    });

    it('still publishes a document when a fatal ends the process, naming what stopped it', function () {
        $output = new BufferedOutput();
        $end = new AgentSpecProcessEnd();
        $formatter = new Agent($output, null, $end);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]));

        // No end(), no publish(): the process died loading the next spec file.
        $end->arrives(new Fatal('Class Mute contains 1 abstract method', getcwd() . '/spec/App/Broken.spec.php', 8));
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        expect($doc['fatal'])->toBe([
            'message' => 'Class Mute contains 1 abstract method',
            'at' => 'spec/App/Broken.spec.php:8',
        ]);
        expect($doc['result']['actionable'])->toBe(1);
        expect($doc['result']['passing'])->toBe(1);
    });

    it('publishes what it has when the process ends with no fatal', function () {
        $output = new BufferedOutput();
        $end = new AgentSpecProcessEnd();
        $formatter = new Agent($output, null, $end);
        $formatter->begin();

        $end->arrives();
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        expect($doc)->not()->toHaveKey('fatal');
        expect($doc['result']['actionable'])->toBe(0);
    });

    it('leaves the published document alone when the process ends after it', function () {
        $output = new BufferedOutput();
        $end = new AgentSpecProcessEnd();
        $formatter = new Agent($output, null, $end);
        $formatter->format(new SuiteResult([]));

        $end->arrives(new Fatal('something later went wrong'));

        expect(substr_count($output->fetch(), '"run_started"'))->toBe(1);
    });

    it('keeps the first thing that stopped the run', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->stopped('Bootstrap file not found: vendor/autoload.php');
        $formatter->stopped('and then everything else broke');
        $formatter->publish();
        $doc = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR);

        expect($doc['fatal']['message'])->toBe('Bootstrap file not found: vendor/autoload.php');
        expect($doc['fatal']['at'])->toBeNull();
    });

    it('omits coverage from the result when none was collected', function () use ($render) {
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        expect($doc['result'])->not()->toHaveKey('coverage');
    });

    it('omits passing examples from the entries but still counts them', function () use ($render) {
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        expect($doc['examples'])->toBe([]);
        expect($doc['result']['passing'])->toBe(1);
        expect($doc['result']['examples'])->toBe(1);
        expect($doc['result']['actionable'])->toBe(0);
    });

    it('reports a failing example, mapping the subject to actual and the target to expected', function () use ($render) {
        // As phpspec records it for expect(3500)->toBe(4000): getExpected() is the
        // subject (3500), getActual() is the matcher target (4000).
        $match = MatchResult::failed(3500, 4000, 'Expected 3500 to be 4000', getcwd() . '/spec/App/Basket.spec.php', 12, null, 'toBe');
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('totals the prices', [$match])]),
        ]));

        $example = $doc['examples'][0];
        expect($example['state'])->toBe('failing');
        expect($example['expected'])->toBe(['matcher' => 'toBe', 'value' => 4000, 'negated' => false]);
        expect($example['actual'])->toBe(3500);
        expect($example['message'])->toBe('Expected 3500 to be 4000');
        expect($example['spec'])->toBe('spec/App/Basket.spec.php:12');
        expect($example['rerun'])->toBe('run spec/App/Basket.spec.php:12');
        expect($example)->not()->toHaveKey('offer');
    });

    it('flags a negated matcher on the expected block', function () use ($render) {
        $match = MatchResult::failed(5, 5, 'Expected 5 not to be 5', getcwd() . '/spec/App/X.spec.php', 1, null, 'toBe', true);
        $example = $render(new SuiteResult([
            new SpecificationResult('App\\X', [new ExampleResult('rejects equality', [$match])]),
        ]))['examples'][0];

        expect($example['expected']['negated'])->toBe(true);
    });

    it('derives a stable id from the full example name so an agent can recompute it', function () use ($render) {
        $example = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('totals the prices', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/Basket.spec.php', 3)]),
            ]),
        ]))['examples'][0];

        expect($example['id'])->toBe(substr(sha1('App\\Basket totals the prices'), 0, 12));
    });

    it('keeps an example id stable when only its line moves', function () use ($render) {
        $atLine = fn (int $line) => $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('totals the prices', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/Basket.spec.php', $line)]),
            ]),
        ]))['examples'][0];

        expect($atLine(12)['id'])->toBe($atLine(40)['id']);
    });

    it('surfaces deprecations on an entry, as lean message-and-location items', function () use ($render) {
        $example = new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/X.spec.php', 3)]);
        $example->setDeprecations([
            ['severity' => E_USER_DEPRECATED, 'message' => 'since 9.0: use bar()', 'file' => getcwd() . '/src/App/X.php', 'line' => 7],
        ]);

        $entry = $render(new SuiteResult([new SpecificationResult('App\\X', [$example])]))['examples'][0];

        expect($entry['deprecations'])->toBe([['message' => 'since 9.0: use bar()', 'at' => 'src/App/X.php:7']]);
    });

    it('surfaces warnings likewise and omits the note keys when empty', function () use ($render) {
        $example = new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/X.spec.php', 3)]);
        $example->setWarnings([
            ['severity' => E_WARNING, 'message' => 'array offset on null', 'file' => getcwd() . '/src/App/X.php', 'line' => 2],
        ]);

        $entry = $render(new SuiteResult([new SpecificationResult('App\\X', [$example])]))['examples'][0];

        expect($entry['warnings'])->toBe([['message' => 'array offset on null', 'at' => 'src/App/X.php:2']]);
        expect($entry)->not()->toHaveKey('deprecations');
        expect($entry)->not()->toHaveKey('notices');
    });

    it('reports an error example with the exception class, message and location', function () use ($render) {
        $errored = new ExampleResult('blows up', [], true);
        $errored->setError(new ExampleError('boom', new \RuntimeException('boom')));

        $example = $render(new SuiteResult([new SpecificationResult('App\\Thing', [$errored])]))['examples'][0];

        expect($example['state'])->toBe('error');
        expect($example['exception']['class'])->toBe('RuntimeException');
        expect($example['exception']['message'])->toBe('boom');
        expect($example['exception']['at'])->toContain(':');
        expect($example['rerun'])->toBe('run ' . $example['spec']);
        expect($example)->not()->toHaveKey('offer');
    });

    it('carries a per-example create_class offer when the error is a missing class', function () use ($render) {
        $errored = new ExampleResult('needs a coupon', [], true);
        $errored->setError(new ExampleError('Class "App\\Coupon" not found', new \Error('Class "App\\Coupon" not found')));

        $example = $render(new SuiteResult([new SpecificationResult('App\\Basket', [$errored])]))['examples'][0];

        expect($example['offer'])->toBe(['action' => 'create_class', 'target' => 'App\\Coupon']);
    });

    it('summarises totals and duration, offers empty for now', function () use ($render) {
        $suite = new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('holds products', [MatchResult::passed()]),
                new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/X.spec.php', 3)]),
            ]),
        ]);
        $suite->setDuration(0.074);

        $result = $render($suite)['result'];

        expect($result['event'])->toBe('summary');
        expect($result['examples'])->toBe(2);
        expect($result['passing'])->toBe(1);
        expect($result['failing'])->toBe(1);
        expect($result['errors'])->toBe(0);
        expect($result['actionable'])->toBe(1);
        expect($result['duration_ms'])->toBe(74);
        expect($result['offers'])->toBe([]);
    });

    it('lists code-generation offers in the summary from the candidate resolver', function () {
        $output = new BufferedOutput();
        $suite = new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('needs a coupon', [], true)]),
        ]);

        $agent = new Agent($output, fn() => ['missingSpecClasses' => ['App\\Coupon']]);
        $agent->format($suite);

        $result = json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR)['result'];
        expect($result['offers'])->toBe([['action' => 'create_class', 'target' => 'App\\Coupon']]);
    });

    it('counts feature steps as steps, not examples, and emits the failing one', function () use ($render) {
        $step = new StepResult('Given a basket', 'failure');
        $step->setError(new \PhpSpec\StoryBDD\StepError('step went wrong', new \RuntimeException('step went wrong')));
        $scenario = new ScenarioResult('Checkout', [$step]);
        $feature = new FeatureResult('Shopping', [$scenario], '/features/shopping.feature');

        $doc = $render(new SuiteResult([$feature]));

        expect($doc['result']['steps'])->toBe(1);
        expect($doc['result']['examples'])->toBe(0);
        expect($doc['examples'][0]['state'])->toBe('failing');
        expect($doc['examples'][0]['example'])->toContain('Given a basket');
        expect($doc['examples'][0]['id'])->toBe(substr(sha1($doc['examples'][0]['example']), 0, 12));
    });

});
