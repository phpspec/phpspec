<?php

use PhpSpec\Report\Formatter\Pretty;
use PhpSpec\Report\Formatter\Pretty\PrettyViews;
use PhpSpec\Result\SuiteResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ContextResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;
use Symfony\Component\Console\Output\BufferedOutput;

final class PrettySpecPoint
{
    public function __construct(public int $x, public int $y) {}
}

// Code under spec that reaches into PhpSpec: the error is raised beyond the spec.
define('BLAME_SPEC_OUTSIDER', sys_get_temp_dir() . '/phpspec_blame_outsider_' . getmypid() . '.php');
if (!function_exists('blame_spec_outsider_reaches_in')) {
    register_shutdown_function(static fn() => @unlink(BLAME_SPEC_OUTSIDER));
    file_put_contents(BLAME_SPEC_OUTSIDER, "<?php\nfunction blame_spec_outsider_reaches_in(): void { \\PhpSpec\\Mock\\Double::getInstance('Nope\\Missing'); }\n");
    require BLAME_SPEC_OUTSIDER;
}
describe(Pretty::class, function() {

    // A response body or a watched log can run to megabytes, and a terminal
    // scrolling for a minute has buried the failure it was meant to explain.
    it("cuts a flood of printed text, saying how much more there was", function() {
        $output = new BufferedOutput();
        PrettyViews::printedOutput($output, str_repeat('x', 4500), 2);
        $written = $output->fetch();

        expect(substr_count($written, 'x'))->toBe(4000);
        expect($written)->toContain('… 500 more characters');
    });

    it("shows short printed text whole, with no note", function() {
        $output = new BufferedOutput();
        PrettyViews::printedOutput($output, "one\ntwo", 2);
        $written = $output->fetch();

        expect($written)->toContain('▏ one');
        expect($written)->not()->toContain('more characters');
    });

    it("formats passing results", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("passes", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("passes");
    });

    it("formats failing results with details", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed("actual_val", "expected_val", "Expected a to be b", __FILE__, __LINE__);
        $example = new ExampleResult("fails here", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("fails here");
        expect($text)->toContain("Failure");
        expect($text)->toContain("Expected a to be b");
    });

    it("reports only the failed expectation, never the passing ones before it", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $message = "Expected true" . " to be false";
        $example = new ExampleResult("fails after passing expectations", [
            MatchResult::passed(),
            MatchResult::passed(),
            MatchResult::failed("actual_val", "expected_val", $message, __FILE__, __LINE__),
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->not()->toContain("Detail::Nothing");
        expect(substr_count($text, $message))->toBe(1);
    });

    it("renders a failure as its sentence, then the value wanted under expected and the value produced under got, colons aligned", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $haystack = "the hay" . "stack text";
        $needle = "the nee" . "dle";
        $example = new ExampleResult("looks for the needle", [
            MatchResult::failed($haystack, $needle, "Expected \"$haystack\" to contain \"$needle\"", __FILE__, __LINE__, null, "toContain", false),
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('Expected "' . $haystack . '" to contain "' . $needle . '"');
        expect($text)->toContain('expected: "' . $needle . '"');
        expect($text)->toContain('got: "' . $haystack . '"');
        expect($text)->not()->toContain('to contain:');
        // The colons align: both labels end at the same column.
        expect($text)->toMatch('~ {2}expected: ~');
        expect($text)->toMatch('~ {7}got: ~');
    });

    it("labels the value a negated matcher did not want as not expected", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("rejects the needle", [
            MatchResult::failed("the needle", "the needle", "Expected \"the needle\" not to be \"the needle\"", __FILE__, __LINE__, null, "toBe", true),
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('not expected: "the needle"');
        expect($text)->toContain('         got: "the needle"');
    });

    it("reads a mock verification as the call wanted and the calls received, not as the arrays that carry them", function() {
        $output = new BufferedOutput();
        $received = ['method' => 'ArrayAccess::offsetGet', 'calls' => [['arguments' => ['b']], ['arguments' => []]]];
        $wanted = ['method' => 'ArrayAccess::offsetGet', 'arguments' => ['a'], 'times' => 'at least 1'];
        $message = 'Expected ArrayAccess::offsetGet() to be called at least 1 time(s), but was called 0 time(s)';
        $example = new ExampleResult("verifies", [MatchResult::failed($received, $wanted, $message, __FILE__, __LINE__, null, 'toBeCalled', false)]);
        $silent = new ExampleResult("verifies nothing", [MatchResult::failed(
            ['method' => 'ArrayAccess::offsetGet', 'calls' => []],
            ['method' => 'ArrayAccess::offsetGet', 'times' => 'exactly 2'],
            $message, __FILE__, __LINE__, null, 'toBeCalledTimes', false,
        )]);
        (new Pretty($output))->format(new SuiteResult([new SpecificationResult("MySpec", [$example, $silent])]));

        $text = $output->fetch();
        expect($text)->toContain($message);
        expect($text)->toContain('expected: at least 1 call to ArrayAccess::offsetGet("a")');
        expect($text)->toContain('got: ArrayAccess::offsetGet("b"), ArrayAccess::offsetGet()');
        expect($text)->toContain('expected: exactly 2 calls to ArrayAccess::offsetGet(any arguments)');
        expect($text)->toContain('got: no calls');
        expect($text)->not()->toContain('method =>');
    });

    it("caps a long multiline value to head and tail around a marker, newlines escaped", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $blob = "\n  Analysing project...\n\n  Refactor App\\TodoList\n\n  Would you like me to refactor that? [Y/n] ";
        $example = new ExampleResult("reads the transcript", [
            MatchResult::failed($blob, "Would you like me to run it now?", "irrelevant", __FILE__, __LINE__, null, "toContain", false),
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain('got: "\n  Analysing project...\n\n  Ref[...]');
        expect($text)->toContain('e me to refactor that? [Y/n] "');
        expect($text)->not()->toContain("Analysing project...\n");          // raw newlines never reach the pair
        expect($text)->toContain('expected: "Would you like me to run it now?"');
    });

    // A failing pair, rendered: the two sides as the reader sees them.
    $pairFor = function (mixed $subject, mixed $target, string $matcher = 'toBe'): string {
        $output = new BufferedOutput();
        $example = new ExampleResult("compares", [MatchResult::failed($subject, $target, "irrelevant", __FILE__, __LINE__, null, $matcher, false)]);
        (new Pretty($output))->format(new SuiteResult([new SpecificationResult("MySpec", [$example])]));

        return $output->fetch();
    };

    it("tells a string from a number of the same digits", function() use ($pairFor) {
        $text = $pairFor('42', 42);

        expect($text)->toContain('expected: 42');
        expect($text)->toContain('got: "42"');
    });

    it("tells null from the string null", function() use ($pairFor) {
        $text = $pairFor(null, 'null');

        expect($text)->toContain('expected: "null"');
        expect($text)->toContain('got: null');
    });

    it("keeps a float's full precision, so 0.1 + 0.2 reads apart from 0.3", function() use ($pairFor) {
        $text = $pairFor(0.1 + 0.2, 0.3);

        expect($text)->toContain('expected: 0.3');
        expect($text)->toContain('got: 0.30000000000000004');
    });

    it("shows an object's properties when its name alone tells nothing", function() use ($pairFor) {
        $text = $pairFor(new PrettySpecPoint(1, 2), new PrettySpecPoint(1, 3), 'toBeLike');

        expect($text)->toContain('expected: PrettySpecPoint{x: 1, y: 3}');
        expect($text)->toContain('got: PrettySpecPoint{x: 1, y: 2}');
    });

    it("points at the first difference of two long strings instead of eliding it", function() use ($pairFor) {
        $text = $pairFor(str_repeat('a', 60) . 'X' . str_repeat('b', 60), str_repeat('a', 60) . 'Y' . str_repeat('b', 60));

        expect($text)->toContain('first difference at offset 60');
        expect($text)->toContain('X');
        expect($text)->toContain('Y');
    });

    it("groups the detail into Failures, Errors, Warnings, Deprecations, Pending and Skipped sections, in that order", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $failing = new ExampleResult("fails", [
            MatchResult::failed("a", "b", "Expected a to be b", __FILE__, __LINE__),
        ]);
        $erroring = new ExampleResult("errors", [], true);
        $erroring->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $warning = new ExampleResult("warns", [MatchResult::passed()]);
        $warning->setWarnings([['severity' => E_WARNING, 'message' => 'a warning', 'file' => __FILE__, 'line' => __LINE__]]);
        $deprecated = new ExampleResult("deprecates", [MatchResult::passed()]);
        $deprecated->setDeprecations([['severity' => E_USER_DEPRECATED, 'message' => 'a deprecation', 'file' => __FILE__, 'line' => __LINE__]]);
        $pending = new ExampleResult("waits", [], isPending: true);
        $skipped = new ExampleResult("skips", [], false, false, true);
        $spec = new SpecificationResult("MySpec", [$failing, $erroring, $warning, $deprecated, $skipped, $pending]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        $positions = [];
        foreach (["Failures:", "Errors:", "Warnings:", "Deprecations:", "Pending:", "Skipped:"] as $header) {
            expect($text)->toContain($header);
            $positions[] = strpos($text, $header);
        }
        $ordered = $positions;
        sort($ordered);
        expect($positions)->toBe($ordered);
    });

    it("prints no section headers when everything passes", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $spec = new SpecificationResult("MySpec", [new ExampleResult("passes", [MatchResult::passed()])]);
        $formatter->format(new SuiteResult([$spec]));

        $text = $output->fetch();
        expect($text)->not()->toContain("Failures:");
        expect($text)->not()->toContain("Pending:");
        expect($text)->not()->toContain("Skipped:");
    });

    it("shows the reason a pending or skipped example gave beside it, and under it in its section", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $pending = new ExampleResult("fetches the rates", [], isPending: true, reason: "Needs the rates API");
        $skipped = new ExampleResult("posts the order", [], isSkipped: true, reason: "No network here");
        $crossed = new ExampleResult("is crossed out", [], isPending: true);
        $formatter->format(new SuiteResult([new SpecificationResult("MySpec", [$pending, $skipped, $crossed])]));

        $text = $output->fetch();
        expect($text)->toContain("○ fetches the rates (Needs the rates API)");
        expect($text)->toContain("- posts the order (No network here)");
        expect($text)->toContain("○ is crossed out\n");
        expect($text)->toContain("Pending:\n\n  • MySpec > fetches the rates\n    Needs the rates API\n\n  • MySpec > is crossed out\n");
        expect($text)->toContain("Skipped:\n\n  • MySpec > posts the order\n    No network here\n");
    });

    it("formats error results with details", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("errors out", [], true);
        $example->setError(new ExampleError("boom", new \RuntimeException("boom")));
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("errors out");
        expect($text)->toContain("boom");
    });

    it("prints the blamed spec line once, with only the frames beyond it underneath", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        try {
            $line = __LINE__ + 1;
            \PhpSpec\Mock\Double::getInstance('Nope\Missing');
        } catch (\LogicException $e) {
            $error = new ExampleError($e->getMessage(), $e);
        }
        $example = new ExampleResult("uses a double", [], true);
        $example->setError($error);
        $suite = new SuiteResult([new SpecificationResult("MySpec", [$example])]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("  at " . __FILE__ . ":" . $line);
        expect(substr_count($text, __FILE__ . ":" . $line))->toBe(1);
    });

    it("points at the spec line an error came through when it was raised beyond the spec, the deeper frames underneath", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        try {
            $line = __LINE__ + 1;
            blame_spec_outsider_reaches_in();
        } catch (\LogicException $e) {
            $error = new ExampleError($e->getMessage(), $e);
        }
        $example = new ExampleResult("reaches in", [], true);
        $example->declaredAt(__FILE__, $line - 7);
        $example->setError($error);

        $formatter->format(new SuiteResult([new SpecificationResult("MySpec", [$example])]));
        $text = $output->fetch();
        expect($text)->toContain("  at " . __FILE__ . ":" . $line);
        expect($text)->toContain(realpath(BLAME_SPEC_OUTSIDER) . ":2");
        expect($text)->not()->toContain(__FILE__ . ":" . ($line - 7));
    });

    it("prints a step's blamed line once, with only the frames beyond it underneath", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        try {
            $line = __LINE__ + 1;
            \PhpSpec\Mock\Double::getInstance('Nope\Missing');
        } catch (\LogicException $e) {
            $error = new StepError($e->getMessage(), $e);
        }
        $erroredStep = new StepResult("When I use a double", "error");
        $erroredStep->setError($error);
        $suite = new SuiteResult([new FeatureResult("Doubling", [new ScenarioResult("Using a double", [$erroredStep])])]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("  at " . __FILE__ . ":" . $line);
        expect(substr_count($text, __FILE__ . ":" . $line))->toBe(1);
    });

    it("formats pending results", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("is pending", [], false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("pending");
    });

    it("formats empty suite", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $suite = new SuiteResult([]);
        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("No specs found");
    });

    it("formats results with context", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("inner test", [$match]);
        $ctx = new ContextResult("my context", [$example]);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("my context");
        expect($text)->toContain("inner test");
    });

    it("formats context with error", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $ctx = new ContextResult("errored context", []);
        $error = new ExampleError("context broke", new \RuntimeException("context broke"));
        $ctx->setError($error);
        $spec = new SpecificationResult("MySpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("errored context");
        expect($text)->toContain("context broke");
    });

    it("formats results in verbose mode", function() {
        $output = new BufferedOutput();
        $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("verbose test", [$match]);
        $example->setDuration(0.123);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("verbose test");
        expect($text)->toContain("ms");
    });

    it("formats results with warnings", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("warns", [$match]);
        $example->setWarnings([
            ['severity' => E_WARNING, 'message' => 'test warning msg', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("test warning msg");
    });

    it("formats failure with expected/actual values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed("hello", "world", "Expected hello to be world", __FILE__, __LINE__);
        $example = new ExampleResult("value mismatch", [$match]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
        expect($text)->toContain("got:");
    });

    it("formats nested context errors", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $failMatch = MatchResult::failed("a", "b", "inner fail", __FILE__, __LINE__);
        $example = new ExampleResult("nested fail", [$failMatch]);
        $ctx = new ContextResult("inner ctx", [$example]);
        $spec = new SpecificationResult("OuterSpec", [$ctx]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("inner ctx");
        expect($text)->toContain("nested fail");
    });

    it("formats failure with boolean values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed(true, false, "Expected true to be false", __FILE__, __LINE__);
        $example = new ExampleResult("bool mismatch", [$match]);
        $spec = new SpecificationResult("BoolSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
        expect($text)->toContain("got:");
    });

    it("formats failure with null value", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed("something", null, "Expected something to be null", __FILE__, __LINE__);
        $example = new ExampleResult("null check", [$match]);
        $spec = new SpecificationResult("NullSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("null");
    });

    it("formats failure with array values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed([1, 2], [3, 4], "Expected arrays to match", __FILE__, __LINE__);
        $example = new ExampleResult("array mismatch", [$match]);
        $spec = new SpecificationResult("ArraySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
    });

    it("formats failure with object values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $match = MatchResult::failed($obj1, $obj2, "Expected objects to match", __FILE__, __LINE__);
        $example = new ExampleResult("obj mismatch", [$match]);
        $spec = new SpecificationResult("ObjSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("stdClass");
    });

    it("formats failure with numeric values", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::failed(42, 99, "Expected 42 to be 99", __FILE__, __LINE__);
        $example = new ExampleResult("num mismatch", [$match]);
        $spec = new SpecificationResult("NumSpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("expected:");
    });

    it("shows the reason a pending or skipped step gave beside it and under its section, leaving a step skipped behind a failure out of the section", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $failed = new StepResult("When it breaks", "failure");
        $failed->setError(new StepError("something broke", new \RuntimeException("something broke")));
        $feature = new FeatureResult("Shop", [
            new ScenarioResult("Checkout", [
                new StepResult("Given a basket", "passed"),
                new StepResult("When I pay", "pending", "Needs the payment gateway"),
                new StepResult("Then I see a receipt", "skipped", "No printer here"),
            ]),
            new ScenarioResult("Broken", [$failed, new StepResult("Then cascade", "skipped")]),
        ]);
        $formatter->format(new SuiteResult([$feature]));

        $text = $output->fetch();
        expect($text)->toContain("○ When I pay (Needs the payment gateway)");
        expect($text)->toContain("- Then I see a receipt (No printer here)");
        expect($text)->toContain("- Then cascade\n");
        expect($text)->toContain("Pending:\n\n  • Shop > Checkout > When I pay\n    Needs the payment gateway\n");
        expect($text)->toContain("Skipped:\n\n  • Shop > Checkout > Then I see a receipt\n    No printer here\n");
        expect($text)->not()->toContain("• Shop > Broken > Then cascade");
    });

    it("reports a step that breaks the same way in several scenarios once, naming the scenarios, instead of one block per scenario", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $broken = static function (): StepResult {
            $step = new StepResult("Given a broken fixture", "error");
            $step->setError(new StepError("fixture down", new \RuntimeException("fixture down")));

            return $step;
        };
        $feature = new FeatureResult("Checkout", [
            new ScenarioResult("Paying by card", [$broken(), new StepResult("When I pay", "skipped")]),
            new ScenarioResult("Paying by cash", [$broken(), new StepResult("When I pay", "skipped")]),
            new ScenarioResult("Printing", [$broken(), new StepResult("When I print", "skipped")]),
        ]);
        $formatter->format(new SuiteResult([$feature]));

        $text = $output->fetch();
        expect(substr_count($text, "RuntimeException: fixture down"))->toBe(1);
        expect($text)->toContain("• Checkout > Given a broken fixture\n    in Paying by card, Paying by cash and Printing\n");
        expect($text)->not()->toContain("• Checkout > Paying by card > Given a broken fixture");
    });

    it("reports a risky example as one that checked nothing, lists it under Risky and counts it apart", function () {
        $output = new BufferedOutput();
        $risky = new ExampleResult("calls the code", [MatchResult::passed()]);
        $risky = new ExampleResult("calls the code", []);
        $risky->markRisky();
        (new Pretty($output))->format(new SuiteResult([new SpecificationResult("MySpec", [$risky, new ExampleResult("checks", [MatchResult::passed()])])]));

        $text = $output->fetch();
        expect($text)->toContain("! calls the code (no expectation)");
        expect($text)->toContain("Risky:\n\n  • MySpec > calls the code\n    No expectation in this example.\n");
        expect($text)->toContain("1 passes, 1 risky");
    });

    it("formats a feature with passing steps", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $steps = [
            new StepResult("Given a step", "passed"),
            new StepResult("When action", "passed"),
            new StepResult("Then result", "passed"),
        ];
        $scenario = new ScenarioResult("Say hello", $steps);
        $feature = new FeatureResult("Greeting", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Feature: Greeting");
        expect($text)->toContain("Say hello");
        expect($text)->toContain("Given a step");
    });

    it("formats a feature with failed step and error", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $failedStep = new StepResult("When it breaks", "failure");
        $failedStep->setError(new StepError("something broke", new \RuntimeException("something broke")));
        $skippedStep = new StepResult("Then skipped", "skipped");

        $scenario = new ScenarioResult("Broken", [$failedStep, $skippedStep]);
        $feature = new FeatureResult("Errors", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Feature: Errors");
        expect($text)->toContain("something broke");
    });

    it("routes a step that threw to the Errors section, never Failures", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $erroredStep = new StepResult("When I complete the task", "error");
        $erroredStep->setError(new StepError("Call to a member function complete() on null", new \Error("Call to a member function complete() on null")));

        $scenario = new ScenarioResult("Completing a task", [$erroredStep]);
        $feature = new FeatureResult("Completing", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Errors:");
        expect($text)->toContain("Call to a member function complete() on null");
        expect($text)->not()->toContain("Failures:");
    });

    it("reports a step's PHP warnings in the Warnings section", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $noisyStep = new StepResult("Given I have added a task", "passed");
        $noisyStep->setWarnings([
            ['severity' => E_WARNING, 'message' => 'Undefined property: StepWorld::$list', 'file' => 'features/steps/adding.steps.php', 'line' => 4],
        ]);

        $scenario = new ScenarioResult("Adding", [$noisyStep]);
        $feature = new FeatureResult("Adding tasks", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Warnings:");
        expect($text)->toContain('Undefined property: StepWorld::$list');
    });

    it("formats a feature with undefined and pending steps", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $steps = [
            new StepResult("Given undefined", "undefined"),
            new StepResult("When pending", "pending"),
        ];
        $scenario = new ScenarioResult("Mixed", $steps);
        $feature = new FeatureResult("States", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("Feature: States");
        expect($text)->toContain("undefined");
        expect($text)->toContain("pending");
    });

    it("formats results with deprecations", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("deprecated call", [$match]);
        $example->setDeprecations([
            ['severity' => E_DEPRECATED, 'message' => 'old function used', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("old function used");
    });

    it("formats results with notices", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $match = MatchResult::passed();
        $example = new ExampleResult("notice fired", [$match]);
        $example->setNotices([
            ['severity' => E_NOTICE, 'message' => 'undefined var', 'file' => __FILE__, 'line' => __LINE__]
        ]);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("undefined var");
    });

    it("formats skipped examples", function() {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("is skipped", [], false, false, true);
        $spec = new SpecificationResult("MySpec", [$example]);
        $suite = new SuiteResult([$spec]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("skipped");
    });

    it("formats feature counts correctly", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $steps = [
            new StepResult("Given passed", "passed"),
            new StepResult("Then failed", "failure"),
        ];
        $scenario = new ScenarioResult("Test", $steps);
        $feature = new FeatureResult("Feature", [$scenario]);
        $suite = new SuiteResult([$feature]);

        $formatter->format($suite);
        $text = $output->fetch();
        expect($text)->toContain("1 feature");
        expect($text)->toContain("1 scenario");
        expect($text)->toContain("2 steps");
    });

    it("names a spec the way a feature is named, bold white and prefixed", function () {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $formatter = new Pretty($output);

        $spec = new SpecificationResult("Wallet", [new ExampleResult("passes", [MatchResult::passed()])]);
        $feature = new FeatureResult("Greeting", [new ScenarioResult("Say hello", [new StepResult("Given a step", "passed")])]);

        $formatter->format(new SuiteResult([$spec, $feature]));
        $text = $output->fetch();
        expect($text)->toContain("\e[37;1mSpec: Wallet\e[39;22m");
        expect($text)->toContain("\e[37;1mFeature: Greeting\e[39;22m");
    });

    it("colours an example title by its outcome, glyph and title together", function () {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $formatter = new Pretty($output);

        $spec = new SpecificationResult("Wallet", [
            new ExampleResult("passes", [MatchResult::passed()]),
            new ExampleResult("fails", [MatchResult::failed("a", "b", "Expected a to be b", __FILE__, __LINE__)]),
            new ExampleResult("breaks", [], true),
            new ExampleResult("waits", [], false, true),
            new ExampleResult("rests", [], false, false, true),
        ]);

        $formatter->format(new SuiteResult([$spec]));
        $text = $output->fetch();
        expect($text)->toContain("\e[32m✓ passes\e[39m");
        expect($text)->toContain("\e[31m✘ fails\e[39m");
        expect($text)->toContain("\e[31m✘ breaks\e[39m");
        expect($text)->toContain("\e[33m○ waits\e[39m");
        expect($text)->toContain("\e[36m- rests\e[39m");
        expect($text)->not()->toContain("(pending)");
        expect($text)->not()->toContain("(skipped)");
    });

    it("colours a step title by its outcome, with the glyphs an example uses", function () {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_NORMAL, true);
        $formatter = new Pretty($output);

        $failed = new StepResult("When it breaks", "failure");
        $failed->setError(new StepError("broke", new \RuntimeException("broke")));
        $scenario = new ScenarioResult("Mixed", [
            new StepResult("Given a step", "passed"),
            $failed,
            new StepResult("Then pending", "pending"),
            new StepResult("And undefined", "undefined"),
            new StepResult("But skipped", "skipped"),
        ]);

        $formatter->format(new SuiteResult([new FeatureResult("States", [$scenario])]));
        $text = $output->fetch();
        expect($text)->toContain("\e[32m✓ Given a step\e[39m");
        expect($text)->toContain("\e[31m✘ When it breaks\e[39m");
        expect($text)->toContain("\e[33m○ Then pending\e[39m");
        expect($text)->toContain("\e[94m? And undefined\e[39m");
        expect($text)->toContain("\e[36m- But skipped\e[39m");
    });

    it("marks a warning with a bare warning sign and names where it was raised", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $example = new ExampleResult("warns", [MatchResult::passed()]);
        $example->setWarnings([
            ['severity' => E_WARNING, 'message' => 'old warning', 'file' => '/project/spec/Wallet.spec.php', 'line' => 7],
        ]);

        $formatter->format(new SuiteResult([new SpecificationResult("Wallet", [$example])]));
        $text = $output->fetch();
        expect($text)->toContain("⚠ old warning (Wallet.spec.php:7)");
        expect($text)->not()->toContain("⚠\u{FE0F}");
    });

    it("shows a step's warning under the step, the way an example's is shown", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $noisy = new StepResult("Given I have added a task", "passed");
        $noisy->setWarnings([
            ['severity' => E_WARNING, 'message' => 'Undefined property: StepWorld::$list', 'file' => 'features/steps/adding.steps.php', 'line' => 4],
        ]);
        $scenario = new ScenarioResult("Adding a task", [$noisy]);

        $formatter->format(new SuiteResult([new FeatureResult("Adding", [$scenario])]));
        $text = $output->fetch();
        expect($text)->toContain("      ⚠ Undefined property: StepWorld::\$list (adding.steps.php:4)");
    });

    it("prints nothing for a spec blocked on a class that does not exist yet, which the run offers instead", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $error = new ExampleError('Class "App\Calculator" not found', new \Error('Class "App\Calculator" not found'));
        $example = new ExampleResult("App\\Calculator", [], true);
        $example->setError($error);
        $context = new ContextResult("App\\Calculator", [$example]);
        $context->setError($error);

        $formatter->format(new SuiteResult([new SpecificationResult("Calculator", [$context])]));
        expect($output->fetch())->toBe("");
    });

    it("keeps the tree and the counts for what did run alongside a blocked spec", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $error = new ExampleError('Class "App\Calculator" not found', new \Error('Class "App\Calculator" not found'));
        $blockedExample = new ExampleResult("App\\Calculator", [], true);
        $blockedExample->setError($error);
        $blocked = new ContextResult("App\\Calculator", [$blockedExample]);
        $blocked->setError($error);
        $ran = new ContextResult("App\\Basket", [new ExampleResult("totals the prices", [MatchResult::passed()])]);

        $formatter->format(new SuiteResult([
            new SpecificationResult("Calculator", [$blocked]),
            new SpecificationResult("Basket", [$ran]),
        ]));
        $text = $output->fetch();
        expect($text)->not()->toContain("Calculator");
        expect($text)->toContain("Spec: Basket");
        expect($text)->toContain("✓ totals the prices");
        expect($text)->toContain("2 specs");
    });

    it("leaves an example's missing class out of the Errors section, since the run offers to generate it", function () {
        $output = new BufferedOutput();
        $formatter = new Pretty($output);

        $error = new ExampleError('Class "App\Report" not found', new \Error('Class "App\Report" not found'));
        $errored = new ExampleResult("builds a report", [], true);
        $errored->setError($error);
        $context = new ContextResult("App\\Basket", [new ExampleResult("totals the prices", [MatchResult::passed()]), $errored]);

        $formatter->format(new SuiteResult([new SpecificationResult("Basket", [$context])]));
        $text = $output->fetch();
        expect($text)->toContain("✘ builds a report");
        expect($text)->not()->toContain("Errors:");
        expect($text)->toContain("2 examples");
    });

    it("shows how long a step took in verbose mode", function () {
        $output = new BufferedOutput();
        $output->setVerbosity(BufferedOutput::VERBOSITY_VERBOSE);
        $formatter = new Pretty($output);

        $step = new StepResult("Given a slow step", "passed");
        $step->setDuration(0.0123);
        $scenario = new ScenarioResult("Waiting", [$step]);

        $formatter->format(new SuiteResult([new FeatureResult("Timing", [$scenario])]));
        $text = $output->fetch();
        expect($text)->toContain("Given a slow step (12.3ms)");
    });

});
