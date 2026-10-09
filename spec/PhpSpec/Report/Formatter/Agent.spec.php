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
use Symfony\Component\Console\Output\OutputInterface;

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

// Code under spec that reaches into PhpSpec: the error is raised beyond the spec.
define('BLAME_SPEC_OUTSIDER', sys_get_temp_dir() . '/phpspec_blame_outsider_' . getmypid() . '.php');
if (!function_exists('blame_spec_outsider_reaches_in')) {
    register_shutdown_function(static fn() => @unlink(BLAME_SPEC_OUTSIDER));
    file_put_contents(BLAME_SPEC_OUTSIDER, "<?php\nfunction blame_spec_outsider_reaches_in(): void { \\PhpSpec\\Mock\\Double::getInstance('Nope\\Missing'); }\n");
    require BLAME_SPEC_OUTSIDER;
}
describe(Agent::class, function () {

    it("addresses an error raised beyond the spec at the spec line it came through, the rerun at the declaring line", function () {
        try {
            $line = __LINE__ + 1;
            blame_spec_outsider_reaches_in();
        } catch (\LogicException $e) {
            $error = new ExampleError($e->getMessage(), $e);
        }
        $example = new ExampleResult("reaches in", [], true);
        $example->declaredAt(__FILE__, $line - 5);
        $example->setError($error);
        $output = new BufferedOutput();

        (new Agent($output))->format(new SuiteResult([new SpecificationResult("MySpec", [$example])]));

        $entry = json_decode(explode("\n", trim($output->fetch()))[1], true, flags: JSON_THROW_ON_ERROR);
        expect($entry['spec'])->toEndWith('Agent.spec.php:' . $line);
        expect($entry['rerun'])->toEndWith('Agent.spec.php:' . ($line - 5));
    });

    it("carries the reason a pending or skipped example gave as its message, and no message without one", function () {
        $pending = new ExampleResult("is pending", [], isPending: true, reason: "Needs the rates API");
        $skipped = new ExampleResult("is skipped", [], isSkipped: true, reason: "No network here");
        $crossed = new ExampleResult("is crossed out", [], isPending: true);
        $output = new BufferedOutput();

        (new Agent($output))->format(new SuiteResult([new SpecificationResult("MySpec", [$pending, $skipped, $crossed])]));

        $lines = explode("\n", trim($output->fetch()));
        $entries = array_map(static fn(string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), array_slice($lines, 1, 3));
        expect($entries[0]['state'])->toBe('pending');
        expect($entries[0]['message'])->toBe('Needs the rates API');
        expect($entries[1]['state'])->toBe('skipped');
        expect($entries[1]['message'])->toBe('No network here');
        expect($entries[2]['state'])->toBe('pending');
        expect($entries[2])->not()->toHaveKey('message');
    });

    // The run answers in JSON Lines. Decoding a line at a time and filing each
    // event by its kind is the whole of what a reader does, so the spec reads
    // the output the same way: the header, the entries as they arrived, and the
    // summary that closed the stream.
    $stream = function (string $output): array {
        $document = ['suite' => null, 'examples' => [], 'result' => null];

        foreach (explode("\n", trim($output)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $event = json_decode($line, true, flags: JSON_THROW_ON_ERROR);

            match ($event['event']) {
                'run_started' => $document['suite'] = $event,
                'example' => $document['examples'][] = $event,
                'fatal' => $document['fatal'] = $event,
                'summary' => $document['result'] = $event,
            };
        }

        return $document;
    };

    $render = function (SuiteResult $suite) use ($stream): array {
        $output = new BufferedOutput();
        (new Agent($output))->format($suite);

        return $stream($output->fetch());
    };

    it('answers in JSON Lines, one self-contained event per line', function () {
        $output = new BufferedOutput();
        (new Agent($output))->format(new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/X.spec.php', 3)]),
            ]),
        ]));

        $lines = explode("\n", trim($output->fetch()));

        expect($lines)->toHaveLength(3);
        foreach ($lines as $line) {
            expect(json_decode($line, true, flags: JSON_THROW_ON_ERROR)['v'])->toBe(2);
        }
        expect(json_decode($lines[0], true)['event'])->toBe('run_started');
        expect(json_decode($lines[1], true)['event'])->toBe('example');
        expect(json_decode($lines[2], true)['event'])->toBe('summary');
    });

    it('reports a failure while the run is still going, before any summary', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [
            new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/X.spec.php', 3)]),
        ]));

        // Nothing has ended the run: the entry is out all the same.
        $written = $output->fetch();

        expect($written)->toContain('"event":"example"');
        expect($written)->not()->toContain('"event":"summary"');
    });

    it('emits a header, entries and a summary', function () use ($render) {
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        expect($doc['suite'])->toBe(['v' => 2, 'event' => 'run_started', 'suite' => 'default', 'seed' => null, 'php' => PHP_VERSION, 'coverage' => false, 'guard' => 'off']);
        expect($doc['examples'])->toBe([]);
        expect($doc['result']['event'])->toBe('summary');
    });

    it('reports the seed and suite it was told about, so a flaky order is reproducible', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->targets('spec,features/');
        $formatter->randomisedWith(424242);
        $formatter->format(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));
        $doc = $stream($output->fetch());

        expect($doc['suite']['seed'])->toBe(424242);
        expect($doc['suite']['suite'])->toBe('spec,features/');
    });

    it('states in the header the mode it runs in, so a reader knows upfront what verdicts to expect', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->runningWith(coverage: true, guard: 'stood down');
        $formatter->format(new SuiteResult([]));
        $doc = $stream($output->fetch());

        expect($doc['suite']['php'])->toBe(PHP_VERSION);
        expect($doc['suite']['coverage'])->toBeTrue();
        expect($doc['suite']['guard'])->toBe('stood down');
    });

    it('carries the remedy for what stopped the run, when there is one', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->stopped('Code coverage requires Xdebug with coverage mode enabled', remedy: 'XDEBUG_MODE=coverage bin/phpspec run --coverage-min=90');
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['fatal']['remedy'])->toBe('XDEBUG_MODE=coverage bin/phpspec run --coverage-min=90');
    });

    it('carries no remedy key when nothing is known to help', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->stopped('Two step definitions share a title');
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['fatal'])->not()->toHaveKey('remedy');
    });

    it('closes the stream once, however often the command publishes it', function () {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->format(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        $formatter->publish();
        $formatter->publish();

        $written = $output->fetch();
        expect(substr_count($written, '"run_started"'))->toBe(1);
        expect(substr_count($written, '"summary"'))->toBe(1);
    });

    it('publishes what it collected even when the run never reached its end', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->targets('./spec');
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [
            new ExampleResult('holds products', [MatchResult::failed(1, 2, 'Expected 1 to be 2', getcwd() . '/spec/App/Basket.spec.php', 9, null, 'toBe')]),
        ]));

        // No end(): a fatal took the process before the last example.
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['result']['failing'])->toBe(1);
        expect($doc['result']['duration_ms'])->toBe(0);
        expect($doc['examples'][0]['example'])->toBe('App\\Basket > holds products');
        // What the run was trying to run is known before the run: a stream cut
        // short still says it.
        expect($doc['suite']['suite'])->toBe('./spec');
    });

    it('carries a missed coverage gate in the summary, and counts it as work left', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]));
        $formatter->end(new SuiteResult([]));
        $formatter->covered(new CoverageVerdict(24.34, 90.0));
        $formatter->publish();
        $doc = $stream($output->fetch());

        // JSON has one number type, so 90.0 comes back as 90.
        expect($doc['result']['coverage']['percent'])->toBe(24.3);
        expect((float) $doc['result']['coverage']['required'])->toBe(90.0);
        expect($doc['result']['coverage']['met'])->toBeFalse();
        expect($doc['result']['failing'])->toBe(0);
        expect($doc['result']['actionable'])->toBe(1);
    });

    it('reports in the summary what --accept-offers wrote, and that nothing has verified it', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [new ExampleResult('applies a coupon', [], true)]));
        $formatter->applied([
            ['id' => 'o_1', 'action' => 'create_class', 'target' => 'App\\Coupon', 'file' => 'src/App/Coupon.php', 'applied' => true],
            ['id' => 'o_2', 'action' => 'create_method', 'target' => 'App\\Coupon::apply', 'file' => 'src/App/Coupon.php', 'applied' => true],
        ]);
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['result']['applied'])->toBe([
            'offers' => [
                ['id' => 'o_1', 'action' => 'create_class', 'target' => 'App\\Coupon', 'file' => 'src/App/Coupon.php', 'applied' => true],
                ['id' => 'o_2', 'action' => 'create_method', 'target' => 'App\\Coupon::apply', 'file' => 'src/App/Coupon.php', 'applied' => true],
            ],
            'files' => ['src/App/Coupon.php'],
            'verified' => false,
        ]);
        expect($doc['result']['errors'])->toBe(1);
    });

    it('keeps an offer that was not applied out of the files written, carrying its reason', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->applied([
            ['id' => 'o_1', 'action' => 'create_class', 'target' => 'App\\Coupon', 'file' => 'src/App/Coupon.php', 'applied' => true],
            ['id' => 'o_2', 'action' => 'create_method', 'target' => 'App\\Basket::total', 'file' => 'src/App/Basket.php', 'applied' => false, 'reason' => "Method 'total' already exists"],
        ]);
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['result']['applied']['files'])->toBe(['src/App/Coupon.php']);
        expect($doc['result']['applied']['offers'][1]['applied'])->toBeFalse();
        expect($doc['result']['applied']['offers'][1]['reason'])->toBe("Method 'total' already exists");
    });

    it('reports coverage without a threshold as met, adding no work', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->begin();
        $formatter->end(new SuiteResult([]));
        $formatter->covered(new CoverageVerdict(72.5));
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['result']['coverage'])->toBe(['percent' => 72.5, 'required' => null, 'met' => true]);
        expect($doc['result']['actionable'])->toBe(0);
    });

    it('still closes the stream when a fatal ends the process, naming what stopped it', function () use ($stream) {
        $output = new BufferedOutput();
        $end = new AgentSpecProcessEnd();
        $formatter = new Agent($output, null, $end);
        $formatter->begin();
        $formatter->printResult(new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]));

        // No end(), no publish(): the process died loading the next spec file.
        $end->arrives(new Fatal('Class Mute contains 1 abstract method', getcwd() . '/spec/App/Broken.spec.php', 8));
        $doc = $stream($output->fetch());

        expect($doc['fatal']['message'])->toBe('Class Mute contains 1 abstract method');
        expect($doc['fatal']['at'])->toBe('spec/App/Broken.spec.php:8');
        expect($doc['result']['actionable'])->toBe(1);
        expect($doc['result']['passing'])->toBe(1);
    });

    it('publishes what it has when the process ends with no fatal', function () use ($stream) {
        $output = new BufferedOutput();
        $end = new AgentSpecProcessEnd();
        $formatter = new Agent($output, null, $end);
        $formatter->begin();

        $end->arrives();
        $doc = $stream($output->fetch());

        expect($doc)->not()->toHaveKey('fatal');
        expect($doc['result']['actionable'])->toBe(0);
    });

    it('leaves the closed stream alone when the process ends after it', function () {
        $output = new BufferedOutput();
        $end = new AgentSpecProcessEnd();
        $formatter = new Agent($output, null, $end);
        $formatter->format(new SuiteResult([]));

        $end->arrives(new Fatal('something later went wrong'));

        expect(substr_count($output->fetch(), '"run_started"'))->toBe(1);
    });

    it('keeps the first thing that stopped the run', function () use ($stream) {
        $output = new BufferedOutput();
        $formatter = new Agent($output);
        $formatter->stopped('Bootstrap file not found: vendor/autoload.php');
        $formatter->stopped('and then everything else broke');
        $formatter->publish();
        $doc = $stream($output->fetch());

        expect($doc['fatal']['message'])->toBe('Bootstrap file not found: vendor/autoload.php');
        expect($doc['fatal']['at'])->toBeNull();
        // The header still leads, even for a run that never began.
        expect($doc['suite']['event'])->toBe('run_started');
    });

    it('joins every failing target into one rerun command, without repeats', function () use ($render) {
        $spec = getcwd() . '/spec/App/Basket.spec.php';
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('totals', [MatchResult::failed(1, 2, 'Expected 1 to be 2', $spec, 12, null, 'toBe')]),
                new ExampleResult('counts', [MatchResult::failed(3, 4, 'Expected 3 to be 4', $spec, 20, null, 'toBe')]),
                new ExampleResult('repeats', [MatchResult::failed(3, 4, 'Expected 3 to be 4', $spec, 20, null, 'toBe')]),
            ]),
        ]));

        expect($doc['result']['rerun'])->toBe('run spec/App/Basket.spec.php:12 spec/App/Basket.spec.php:20');
    });

    it('offers no rerun command when nothing failed', function () use ($render) {
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        expect($doc['result'])->not()->toHaveKey('rerun');
    });

    it('omits coverage from the summary when none was collected', function () use ($render) {
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

    // What a reader gets when it asks for everything: the same stream, with
    // the passing entries in it.
    $renderVerbose = function (SuiteResult $suite) use ($stream): array {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        (new Agent($output))->format($suite);

        return $stream($output->fetch());
    };

    it('reports a passing example under verbose output, addressed by the line that declares it', function () use ($renderVerbose) {
        $passing = new ExampleResult('holds products', [MatchResult::passed()]);
        $passing->declaredAt(getcwd() . '/spec/App/Basket.spec.php', 7);

        $doc = $renderVerbose(new SuiteResult([new SpecificationResult('App\\Basket', [$passing])]));

        expect($doc['examples'])->toBe([[
            'v' => 2,
            'event' => 'example',
            'id' => substr(sha1('App\\Basket > holds products'), 0, 12),
            'example' => 'App\\Basket > holds products',
            'state' => 'passing',
            'spec' => 'spec/App/Basket.spec.php:7',
            'rerun' => 'run spec/App/Basket.spec.php:7',
            'rerun_argv' => ['run', 'spec/App/Basket.spec.php:7', '--format=agent'],
        ]]);
        expect($doc['result']['passing'])->toBe(1);
        expect($doc['result']['actionable'])->toBe(0);
    });

    it('keeps passing entries out of the summary rerun command', function () use ($renderVerbose) {
        $passing = new ExampleResult('holds products', [MatchResult::passed()]);
        $passing->declaredAt(getcwd() . '/spec/App/Basket.spec.php', 7);
        $failing = new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/Basket.spec.php', 12)]);

        $doc = $renderVerbose(new SuiteResult([new SpecificationResult('App\\Basket', [$passing, $failing])]));

        expect($doc['result']['rerun'])->toBe('run spec/App/Basket.spec.php:12');
    });

    it('reports a passing example that came back without its site by name alone', function () use ($renderVerbose) {
        // A parallel worker's JUnit report knows no declaration line.
        $doc = $renderVerbose(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('holds products', [MatchResult::passed()])]),
        ]));

        expect($doc['examples'][0]['state'])->toBe('passing');
        expect($doc['examples'][0])->not()->toHaveKey('spec');
        expect($doc['examples'][0])->not()->toHaveKey('rerun');
    });

    it('reports a passing scenario under verbose output, addressed by its line', function () use ($renderVerbose) {
        $step = new StepResult('a working step', 'passed');
        $scenario = new ScenarioResult('Counting up', [$step], 2);

        $doc = $renderVerbose(new SuiteResult([
            new FeatureResult('Counting', [$scenario], getcwd() . '/features/counting.feature'),
        ]));

        expect($doc['examples'])->toHaveLength(1);
        expect($doc['examples'][0]['example'])->toBe('Counting > Counting up');
        expect($doc['examples'][0]['state'])->toBe('passing');
        expect($doc['examples'][0]['rerun'])->toBe('run features/counting.feature:2');
        expect($doc['examples'][0])->not()->toHaveKey('steps');
        expect($doc['result'])->not()->toHaveKey('rerun');
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
        expect($example['expectation'])->toBe(['matcher' => 'toBe', 'expected' => 4000, 'actual' => 3500, 'negated' => false]);
        expect($example['message'])->toBe('Expected 3500 to be 4000');
        expect($example['spec'])->toBe('spec/App/Basket.spec.php:12');
        expect($example['rerun'])->toBe('run spec/App/Basket.spec.php:12');
        expect($example)->not()->toHaveKey('offer');
    });

    it('points at the first difference between two strings, so a reader need not diff them by eye', function () use ($render) {
        $match = MatchResult::failed("total: 0\n", 'total: 0', 'no', getcwd() . '/spec/App/Basket.spec.php', 12, null, 'toBe');

        $example = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('prints the total', [$match])]),
        ]))['examples'][0];

        expect($example['expectation']['diff'])->toBe(['offset' => 8, 'expected' => '', 'actual' => "\n"]);
    });

    it('carries no diff when the two sides are not strings or arrays alike', function () use ($render) {
        $match = MatchResult::failed(3500, 4000, 'no', getcwd() . '/spec/App/Basket.spec.php', 12, null, 'toBe');

        $example = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('totals', [$match])]),
        ]))['examples'][0];

        expect($example['expectation'])->not()->toHaveKey('diff');
    });

    it('hands over the rerun as arguments too, carrying the format, so an agent need not rebuild the command', function () use ($render) {
        $match = MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/Basket.spec.php', 12);
        $doc = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('totals', [$match]),
                new ExampleResult('adds', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/Basket.spec.php', 20)]),
            ]),
        ]));

        expect($doc['examples'][0]['rerun_argv'])->toBe(['run', 'spec/App/Basket.spec.php:12', '--format=agent']);
        expect($doc['result']['rerun_argv'])->toBe(['run', 'spec/App/Basket.spec.php:12', 'spec/App/Basket.spec.php:20', '--format=agent']);
    });

    it('keeps an entry whole when a compared value is a float JSON cannot hold', function () use ($render) {
        $match = MatchResult::failed(INF, 1.0, 'Expected INF to be 1.0', getcwd() . '/spec/App/Basket.spec.php', 12, null, 'toBe');

        $example = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('reports infinity', [$match])]),
        ]))['examples'][0];

        expect($example['example'])->toBe('App\\Basket > reports infinity');
        expect($example['expectation']['actual'])->toBe('INF');
        expect($example['expectation']['expected'])->toBe(1.0);
    });

    it('keeps an entry whole when a value or the printed output holds bytes that are not UTF-8', function () use ($render) {
        $failing = new ExampleResult('reads bytes', [MatchResult::failed("bad\xFF", 'good', 'no', getcwd() . '/spec/App/Basket.spec.php', 12, null, 'toBe')]);
        $failing->setOutput("output\xFF");

        $example = $render(new SuiteResult([new SpecificationResult('App\\Basket', [$failing])]))['examples'][0];

        expect($example['state'])->toBe('failing');
        expect($example['expectation']['actual'])->toBe("bad\u{FFFD}");
        expect($example['output'])->toBe("output\u{FFFD}");
    });

    it('keeps a null value an anonymous matcher failed on, having a site to prove it real', function () use ($render) {
        // A custom or predicate matcher reaches phpspec through __call and stays
        // nameless: null everywhere except the site. "Your code produced null"
        // is exactly what the reader needs.
        $match = MatchResult::failed(null, null, 'Expected the greeter to have greeted', getcwd() . '/spec/App/X.spec.php', 7);

        $example = $render(new SuiteResult([
            new SpecificationResult('App\\X', [new ExampleResult('greets', [$match])]),
        ]))['examples'][0];

        expect($example['expectation'])->toBe(['matcher' => null, 'expected' => null, 'actual' => null, 'negated' => false]);
    });

    it('reports only the message when a failure came back without its site', function () use ($render) {
        // What a parallel worker's JUnit report can carry: the message, and a
        // stand-in expectation comparing nothing with nothing.
        $match = MatchResult::failed(null, null, 'Expected 3500 to be 4000', '', 0);

        $example = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('totals', [$match])]),
        ]))['examples'][0];

        expect($example['message'])->toBe('Expected 3500 to be 4000');
        expect($example)->not()->toHaveKey('expectation');
        // And no location invented from the parts it does not have.
        expect($example)->not()->toHaveKey('spec');
        expect($example)->not()->toHaveKey('rerun');
        expect($example)->not()->toHaveKey('rerun_argv');
    });

    it('flags a negated matcher on the expected block', function () use ($render) {
        $match = MatchResult::failed(5, 5, 'Expected 5 not to be 5', getcwd() . '/spec/App/X.spec.php', 1, null, 'toBe', true);
        $example = $render(new SuiteResult([
            new SpecificationResult('App\\X', [new ExampleResult('rejects equality', [$match])]),
        ]))['examples'][0];

        expect($example['expectation']['negated'])->toBe(true);
    });

    it('derives a stable id from the full example name so an agent can recompute it', function () use ($render) {
        $example = $render(new SuiteResult([
            new SpecificationResult('App\\Basket', [
                new ExampleResult('totals the prices', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/Basket.spec.php', 3)]),
            ]),
        ]))['examples'][0];

        expect($example['id'])->toBe(substr(sha1('App\\Basket > totals the prices'), 0, 12));
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

    it('carries what an example printed, so the dump explaining it is not lost', function () use ($render) {
        $example = new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/X.spec.php', 3)]);
        $example->setOutput("the subject said this\n");

        $entry = $render(new SuiteResult([new SpecificationResult('App\\X', [$example])]))['examples'][0];

        expect($entry['output'])->toBe("the subject said this\n");
    });

    it('says nothing about output when nothing was printed', function () use ($render) {
        $entry = $render(new SuiteResult([
            new SpecificationResult('App\\X', [
                new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/X.spec.php', 3)]),
            ]),
        ]))['examples'][0];

        expect($entry)->not()->toHaveKey('output');
    });

    it('caps a flood of printed output, marking how much there was', function () use ($render) {
        $example = new ExampleResult('totals', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/App/X.spec.php', 3)]);
        $example->setOutput(str_repeat('x', 5000));

        $entry = $render(new SuiteResult([new SpecificationResult('App\\X', [$example])]))['examples'][0];

        expect($entry['output']['truncated'])->toBeTrue();
        expect($entry['output']['length'])->toBe(5000);
        expect(strlen($entry['output']['value']))->toBe(4000);
    });

    it('carries what a scenario printed, whichever of its steps did the printing', function () use ($render) {
        $ran = new StepResult('Given I run the counter', 'passed');
        $ran->setOutput('the counter said 2');
        $checked = new StepResult('Then it should have counted 3', 'failure');
        $checked->setError(new \PhpSpec\StoryBDD\StepError('Expected 2 to be 3', new \RuntimeException('Expected 2 to be 3')));

        $entry = $render(new SuiteResult([
            new FeatureResult('Counting', [new ScenarioResult('Counting up', [$ran, $checked], 2)], 'features/counting.feature'),
        ]))['examples'][0];

        // The step that printed it passed; the entry is what a reader acts on.
        expect($entry['output'])->toBe('the counter said 2');
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

    // An error whose site is somewhere other than where it was thrown, as PHP
    // reports one thrown inside the code under test.
    $thrownAt = function (string $message, string $file, int $line): \Throwable {
        $error = new \RuntimeException($message);
        foreach (['file' => $file, 'line' => $line] as $property => $value) {
            $ref = new \ReflectionProperty(\Exception::class, $property);
            $ref->setValue($error, $value);
        }

        return $error;
    };

    it('addresses an error thrown inside the code under test by the line that declares the example', function () use ($render, $thrownAt) {
        $errored = new ExampleResult('adds cents', [], true);
        $errored->declaredAt(getcwd() . '/spec/App/Money.spec.php', 7);
        $errored->setError(new ExampleError('not yet', $thrownAt('not yet', getcwd() . '/src/App/Money.php', 9)));

        $example = $render(new SuiteResult([new SpecificationResult('App\\Money', [$errored])]))['examples'][0];

        expect($example['spec'])->toBe('spec/App/Money.spec.php:7');
        expect($example['rerun'])->toBe('run spec/App/Money.spec.php:7');
        expect($example['exception']['at'])->toBe('src/App/Money.php:9');
    });

    it('keeps the site of an error that surfaced in the spec file itself, re-running by the declaring line', function () use ($render, $thrownAt) {
        $errored = new ExampleResult('adds cents', [], true);
        $errored->declaredAt(getcwd() . '/spec/App/Money.spec.php', 7);
        $errored->setError(new ExampleError('boom', $thrownAt('boom', getcwd() . '/spec/App/Money.spec.php', 12)));

        $example = $render(new SuiteResult([new SpecificationResult('App\\Money', [$errored])]))['examples'][0];

        expect($example['spec'])->toBe('spec/App/Money.spec.php:12');
        expect($example['rerun'])->toBe('run spec/App/Money.spec.php:7');
    });

    it('addresses a failure asserted in a helper by the line that declares the example', function () use ($render) {
        $failing = new ExampleResult('adds cents', [MatchResult::failed(1, 2, 'no', getcwd() . '/spec/support/helpers.php', 40)]);
        $failing->declaredAt(getcwd() . '/spec/App/Money.spec.php', 7);

        $example = $render(new SuiteResult([new SpecificationResult('App\\Money', [$failing])]))['examples'][0];

        expect($example['spec'])->toBe('spec/App/Money.spec.php:7');
        expect($example['rerun'])->toBe('run spec/App/Money.spec.php:7');
    });

    it('carries a per-example create_class offer when the error is a missing class', function () use ($render) {
        $errored = new ExampleResult('needs a coupon', [], true);
        $errored->setError(new ExampleError('Class "App\\Coupon" not found', new \Error('Class "App\\Coupon" not found')));

        $example = $render(new SuiteResult([new SpecificationResult('App\\Basket', [$errored])]))['examples'][0];

        expect($example['offer']['action'])->toBe('create_class');
        expect($example['offer']['target'])->toBe('App\\Coupon');
        expect($example['offer']['id'])->toStartWith('o_');
    });

    it('summarises totals and duration, and says nothing about offers when there are none', function () use ($render) {
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
        expect($result)->not()->toHaveKey('offers');
    });

    it('lists code-generation offers in the summary from the candidate resolver', function () use ($stream) {
        $output = new BufferedOutput();
        $suite = new SuiteResult([
            new SpecificationResult('App\\Basket', [new ExampleResult('needs a coupon', [], true)]),
        ]);

        $agent = new Agent($output, fn() => ['missingSpecClasses' => ['App\\Coupon' => 'App\\Basket']]);
        $agent->format($suite);

        $result = $stream($output->fetch())['result'];
        expect($result['offers'][0]['action'])->toBe('create_class');
        expect($result['offers'][0]['target'])->toBe('App\\Coupon');
        expect($result['offers'][0]['id'])->toStartWith('o_');
    });

    it('carries the reason a pending or skipped step gave on the step, and on the scenario when nothing failed', function () use ($render) {
        $feature = new FeatureResult('Shopping', [
            new ScenarioResult('Checkout', [
                new StepResult('Given a basket', 'passed'),
                new StepResult('When I pay', 'pending', 'Needs the payment gateway'),
                new StepResult('Then I see a receipt', 'skipped', 'No printer here'),
            ]),
        ], '/features/shopping.feature');

        $doc = $render(new SuiteResult([$feature]));

        expect($doc['examples'][0]['state'])->toBe('pending');
        expect($doc['examples'][0]['message'])->toBe('Needs the payment gateway');
        expect($doc['examples'][0]['steps'][0])->toBe(['title' => 'When I pay', 'state' => 'pending', 'message' => 'Needs the payment gateway']);
        expect($doc['examples'][0]['steps'][1])->toBe(['title' => 'Then I see a receipt', 'state' => 'skipped', 'message' => 'No printer here']);
    });

    it('counts a story run in scenarios and steps, never in examples', function () use ($render) {
        $step = new StepResult('Given a basket', 'failure');
        $step->setError(new \PhpSpec\StoryBDD\StepError('step went wrong', new \RuntimeException('step went wrong')));
        $scenario = new ScenarioResult('Checkout', [$step, new StepResult('When I pay', 'skipped')]);
        $feature = new FeatureResult('Shopping', [$scenario], '/features/shopping.feature');

        $doc = $render(new SuiteResult([$feature]));

        expect($doc['result']['steps'])->toBe(2);
        expect($doc['result']['scenarios'])->toBe(1);
        expect($doc['result']['examples'])->toBe(0);
        // One scenario is one thing to fix, however many steps it took with it.
        expect($doc['result']['failing'])->toBe(1);
        expect($doc['examples'])->toHaveLength(1);
        expect($doc['examples'][0]['state'])->toBe('failing');
        expect($doc['examples'][0]['example'])->toBe('Shopping > Checkout');
        expect($doc['examples'][0]['message'])->toBe('step went wrong');
        expect($doc['examples'][0]['id'])->toBe(substr(sha1($doc['examples'][0]['example']), 0, 12));
    });

    it('carries the steps that were not passing, so the broken one is named', function () use ($render) {
        $step = new StepResult('Given a basket', 'failure');
        $step->setError(new \PhpSpec\StoryBDD\StepError('step went wrong', new \RuntimeException('step went wrong')));
        $feature = new FeatureResult('Shopping', [
            new ScenarioResult('Checkout', [
                new StepResult('Given a shop', 'passed'),
                $step,
                new StepResult('When I pay', 'skipped'),
            ], 4),
        ], 'features/shopping.feature');

        $doc = $render(new SuiteResult([$feature]));

        expect($doc['examples'][0]['steps'])->toBe([
            ['title' => 'Given a basket', 'state' => 'failing', 'message' => 'step went wrong'],
            ['title' => 'When I pay', 'state' => 'skipped'],
        ]);
    });

    it('hands over the values a step did not match, on the step and on the entry', function () use ($render) {
        $step = new StepResult('Given I count 2 items', 'failure');
        $step->setError(new \PhpSpec\StoryBDD\StepError('Expected 2 to be 3', new \RuntimeException('Expected 2 to be 3')));
        $step->setMatch(MatchResult::failed(2, 3, 'Expected 2 to be 3', getcwd() . '/features/steps/counting.steps.php', 4, null, 'toBe'));

        $entry = $render(new SuiteResult([
            new FeatureResult('Counting', [new ScenarioResult('Counting up', [$step], 2)], 'features/counting.feature'),
        ]))['examples'][0];

        // Hoisted onto the entry, next to the message it already hoists.
        expect($entry['expectation'])->toBe(['matcher' => 'toBe', 'expected' => 3, 'actual' => 2, 'negated' => false]);
        // And on the step that made it, with the line the expectation lives on.
        expect($entry['steps'][0]['expectation']['expected'])->toBe(3);
        expect($entry['steps'][0]['expectation']['actual'])->toBe(2);
        expect($entry['steps'][0]['at'])->toBe('features/steps/counting.steps.php:4');
    });

    it('leaves a thrown step to its message, having no expectation to report', function () use ($render) {
        $step = new StepResult('Given a broken step', 'error');
        $step->setError(new \PhpSpec\StoryBDD\StepError('this step is broken', new \RuntimeException('this step is broken')));

        $entry = $render(new SuiteResult([
            new FeatureResult('Counting', [new ScenarioResult('Counting up', [$step], 2)], 'features/counting.feature'),
        ]))['examples'][0];

        expect($entry)->not()->toHaveKey('expectation');
        expect($entry['steps'][0])->not()->toHaveKey('expectation');
    });

    it('names a scenario by feature and scenario, so two never share an id', function () use ($render) {
        $failing = function (string $title): StepResult {
            $step = new StepResult($title, 'error');
            $step->setError(new \PhpSpec\StoryBDD\StepError('this step is broken', new \RuntimeException('this step is broken')));

            return $step;
        };

        $doc = $render(new SuiteResult([
            new FeatureResult('Counting', [
                // The same step text in both, which is what used to collide.
                new ScenarioResult('Counting up', [$failing('Given a broken step')], 2),
                new ScenarioResult('Counting down', [$failing('Given a broken step')], 5),
            ], 'features/counting.feature'),
        ]));

        expect($doc['examples'][0]['example'])->toBe('Counting > Counting up');
        expect($doc['examples'][1]['example'])->toBe('Counting > Counting down');
        expect($doc['examples'][0]['id'])->not()->toBe($doc['examples'][1]['id']);
    });

    it('reports a scenario once however often a step title repeats inside it', function () use ($render) {
        $failing = function (string $title, string $state = 'error'): StepResult {
            $step = new StepResult($title, $state);
            $step->setError(new \PhpSpec\StoryBDD\StepError('this step is broken', new \RuntimeException('this step is broken')));

            return $step;
        };

        $doc = $render(new SuiteResult([
            new FeatureResult('Twice', [
                new ScenarioResult('The same step twice', [
                    $failing('Given a broken step'),
                    new StepResult('Given a broken step', 'skipped'),
                ], 2),
            ], 'features/twice.feature'),
        ]));

        expect($doc['examples'])->toHaveLength(1);
        expect($doc['examples'][0]['example'])->toBe('Twice > The same step twice');
    });

    it('addresses a failing scenario by the line that re-runs it', function () use ($render) {
        $step = new StepResult('Given a broken step', 'error');
        $step->setError(new \PhpSpec\StoryBDD\StepError('this step is broken', new \RuntimeException('this step is broken')));

        $doc = $render(new SuiteResult([
            new FeatureResult('Counting', [new ScenarioResult('Counting up', [$step], 5)], 'features/counting.feature'),
        ]));

        expect($doc['examples'][0]['spec'])->toBe('features/counting.feature:5');
        expect($doc['examples'][0]['rerun'])->toBe('run features/counting.feature:5');
        expect($doc['result']['rerun'])->toBe('run features/counting.feature:5');
    });

    it('leaves a scenario waiting on undefined steps unaddressed, so the rerun stays about failures', function () use ($render) {
        $doc = $render(new SuiteResult([
            new FeatureResult('Counting', [
                new ScenarioResult('Counting up', [new StepResult('Given nothing yet', 'undefined')], 2),
            ], 'features/counting.feature'),
        ]));

        expect($doc['examples'][0]['state'])->toBe('pending');
        expect($doc['examples'][0])->not()->toHaveKey('rerun');
        expect($doc['result'])->not()->toHaveKey('rerun');
    });

    it('leaves a passing scenario out, location and all', function () use ($render) {
        $doc = $render(new SuiteResult([
            new FeatureResult('Counting', [
                new ScenarioResult('Counting up', [new StepResult('Given a good step', 'passed')], 2),
            ], 'features/counting.feature'),
        ]));

        expect($doc['examples'])->toBe([]);
        expect($doc['result'])->not()->toHaveKey('rerun');
    });

});
