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

        expect($doc['suite'])->toBe(['v' => 2, 'event' => 'run_started', 'suite' => 'default', 'seed' => null]);
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

        $agent = new Agent($output, fn() => ['missingSpecClasses' => ['App\\Coupon']]);
        $agent->format($suite);

        $result = $stream($output->fetch())['result'];
        expect($result['offers'][0]['action'])->toBe('create_class');
        expect($result['offers'][0]['target'])->toBe('App\\Coupon');
        expect($result['offers'][0]['id'])->toStartWith('o_');
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
