<?php

use PhpSpec\ObjectName;
use PhpSpec\Parallel\Wire;
use PhpSpec\Report\ReportedObject;
use PhpSpec\Report\Typed;
use PhpSpec\Result\ContextResult;
use PhpSpec\Result\ExampleResult;
use PhpSpec\Result\FeatureResult;
use PhpSpec\Result\MatchResult;
use PhpSpec\Result\ScenarioResult;
use PhpSpec\Result\SpecificationResult;
use PhpSpec\Result\StepResult;
use PhpSpec\Specification\ExampleError;
use PhpSpec\StoryBDD\StepError;

final class WirePoint
{
    public function __construct(public int $x, public int $y) {}
}

describe(Wire::class, function () {

    $roundTrip = static function (SpecificationResult|FeatureResult $result): SpecificationResult|FeatureResult {
        $wire = new Wire();
        $line = $wire->encode($result);
        expect(json_decode($line, true, flags: JSON_THROW_ON_ERROR))->toBeOfType('array');

        return $wire->decode($line);
    };

    it("carries a specification with its path, and each example with its title and its state", function () use ($roundTrip) {
        $spec = new SpecificationResult('Basket', [
            new ExampleResult('passes', [MatchResult::passed(), MatchResult::passed()]),
            new ExampleResult('fails', [MatchResult::failed(1, 2, 'Expected 1 to be 2', __FILE__, __LINE__)]),
            new ExampleResult('errors', [], isError: true),
            new ExampleResult('waits', [], isPending: true, reason: 'Needs the rates API'),
            new ExampleResult('stays home', [], isSkipped: true, reason: 'No network here'),
            $risky = new ExampleResult('checks nothing', []),
        ], 'spec/App/Basket.spec.php');
        $risky->markRisky();

        $back = $roundTrip($spec);

        expect($back->getTitle())->toBe('Basket');
        expect($back->getPath())->toBe('spec/App/Basket.spec.php');
        $examples = $back->getResults();
        expect(array_map(static fn(ExampleResult $e): string => $e->getTitle(), $examples))->toBe(['passes', 'fails', 'errors', 'waits', 'stays home', 'checks nothing']);
        expect($examples[5]->isRisky())->toBeTrue();
        expect($examples[0]->isRisky())->toBeFalse();
        expect(count($examples[0]->getResults()))->toBe(2);
        expect($examples[0]->isFailure())->toBeFalse();
        expect($examples[1]->isFailure())->toBeTrue();
        expect($examples[2]->isError())->toBeTrue();
        expect($examples[3]->isPending())->toBeTrue();
        expect($examples[3]->getReason())->toBe('Needs the rates API');
        expect($examples[4]->isSkipped())->toBeTrue();
        expect($examples[4]->isPending())->toBeFalse();
        expect($examples[4]->getReason())->toBe('No network here');
    });

    it("carries a failed expectation whole: both values, the message, the matcher, its negation, an implied target, the fake expression and the site", function () use ($roundTrip) {
        $line = __LINE__;
        $failed = MatchResult::failed(['a' => 1, 'b' => [true, null, 1.5]], 'text', 'Expected [...] to be like "text"', __FILE__, $line, 'return "text";', 'toBeLike', true, false);
        $implied = MatchResult::failed(false, 'N/A', 'Expected false to be true', __FILE__, $line, null, 'toBeTrue', false, true);

        $back = $roundTrip(new SpecificationResult('Basket', [new ExampleResult('fails', [$failed, $implied])]));

        [$match, $other] = $back->getResults()[0]->getResults();
        expect($match->isFailure())->toBeTrue();
        expect($match->getExpected())->toBe(['a' => 1, 'b' => [true, null, 1.5]]);
        expect($match->getActual())->toBe('text');
        expect($match->getMessage())->toBe('Expected [...] to be like "text"');
        expect($match->getMatcher())->toBe('toBeLike');
        expect($match->isNegated())->toBeTrue();
        expect($match->isTargetImplied())->toBeFalse();
        expect($match->getFakeExpression())->toBe('return "text";');
        expect($match->getFile())->toBe(__FILE__);
        expect($match->getLine())->toBe($line);
        expect($match->getCode())->toHaveKey($line);
        expect($other->isTargetImplied())->toBeTrue();
        expect($other->getMatcher())->toBe('toBeTrue');
    });

    it("carries an object by its name and the way it is shown, on its own and inside an array, so every formatter reads it as the worker did", function () use ($roundTrip) {
        $point = new WirePoint(1, 2);
        $failed = MatchResult::failed($point, ['at' => $point, 'n' => 3], 'Expected Point to be like [...]', __FILE__, __LINE__, null, 'toBeLike');

        $back = $roundTrip(new SpecificationResult('Geometry', [new ExampleResult('compares', [$failed])]));

        $match = $back->getResults()[0]->getResults()[0];
        expect($match->getExpected())->toBeAnInstanceOf(ReportedObject::class);
        expect(ObjectName::of($match->getExpected()))->toBe(ObjectName::of($point));
        expect(Typed::value($match->getExpected()))->toBe(Typed::value($point));
        expect(Typed::value($match->getExpected()))->toBe('WirePoint{x: 1, y: 2}');
        expect($match->getActual()['n'])->toBe(3);
        expect(Typed::value($match->getActual()))->toBe(Typed::value(['at' => $point, 'n' => 3]));
    });

    it("carries a float JSON has no word for, and a string that is not UTF-8, without breaking the line", function () use ($roundTrip) {
        $failed = MatchResult::failed(NAN, [INF, -INF], 'Expected NAN to be [...]', __FILE__, __LINE__, null, 'toBe');
        $bytes = MatchResult::failed("\xB1\x31", 'x', 'Expected bytes', __FILE__, __LINE__, null, 'toBe');

        $back = $roundTrip(new SpecificationResult('Numbers', [new ExampleResult('compares', [$failed, $bytes])]));

        [$match, $other] = $back->getResults()[0]->getResults();
        expect(is_nan($match->getExpected()))->toBeTrue();
        expect($match->getActual())->toBe([INF, -INF]);
        expect($other->getExpected())->toBeOfType('string');
    });

    it("carries an error with its type, its site and the frames of the user's code, so the blame and the spec line survive", function () use ($roundTrip) {
        try {
            $line = __LINE__ + 1;
            throw new LogicException('boom');
        } catch (LogicException $e) {
            $error = new ExampleError('boom', $e);
        }
        $example = new ExampleResult('errors', [], isError: true);
        $example->setError($error);
        $example->declaredAt(__FILE__, $line - 3);

        $back = $roundTrip(new SpecificationResult('Basket', [$example]));

        $rebuilt = $back->getResults()[0]->getError();
        expect($rebuilt->getMessage())->toBe('boom');
        expect($rebuilt->getType())->toBe('LogicException');
        expect($rebuilt->getFile())->toBe(__FILE__);
        expect($rebuilt->getLine())->toBe($line);
        expect($rebuilt->blame())->toBe($error->blame());
        expect($rebuilt->lineIn(__FILE__))->toBe($line);
        expect(array_map(static fn(array $f): string => $f['file'] . ':' . $f['line'], $rebuilt->getFilteredTrace()))
            ->toBe(array_map(static fn(array $f): string => $f['file'] . ':' . $f['line'], $error->getFilteredTrace()));
        expect($rebuilt->getSurroundingCode())->toBe($error->getSurroundingCode());
        expect($back->getResults()[0]->getFile())->toBe(__FILE__);
        expect($back->getResults()[0]->getLine())->toBe($line - 3);
    });

    it("carries where an example was declared, how long it ran, what it printed, what it handed over, and its warnings, deprecations and notices", function () use ($roundTrip) {
        $example = new ExampleResult('passes', [MatchResult::passed()]);
        $example->declaredAt('/project/spec/App/Basket.spec.php', 9);
        $example->setDuration(0.125);
        $example->setOutput("printed\n");
        $example->setAttachments(['log' => 'line one', 'page' => ['error' => 'could not read']]);
        $example->setWarnings([['severity' => E_WARNING, 'message' => 'a warning', 'file' => '/project/src/A.php', 'line' => 3]]);
        $example->setDeprecations([['severity' => E_USER_DEPRECATED, 'message' => 'old', 'file' => '/project/src/A.php', 'line' => 4]]);
        $example->setNotices([['severity' => E_NOTICE, 'message' => 'hm', 'file' => '/project/src/A.php', 'line' => 5]]);

        $back = $roundTrip(new SpecificationResult('Basket', [$example]))->getResults()[0];

        expect($back->getFile())->toBe('/project/spec/App/Basket.spec.php');
        expect($back->getLine())->toBe(9);
        expect($back->getDuration())->toBe(0.125);
        expect($back->getOutput())->toBe("printed\n");
        expect($back->getAttachments())->toBe(['log' => 'line one', 'page' => ['error' => 'could not read']]);
        expect($back->getWarnings())->toBe($example->getWarnings());
        expect($back->getDeprecations())->toBe($example->getDeprecations());
        expect($back->getNotices())->toBe($example->getNotices());
    });

    it("carries a context inside a context, and a context that errored, so a spec blocked on a missing class still says so", function () use ($roundTrip) {
        $inner = new ContextResult('when empty', [new ExampleResult('totals nothing', [MatchResult::passed()])]);
        $blocked = new ContextResult('App\Coupon', [new ExampleResult('App\Coupon', [], isError: true)]);
        $blocked->setError(new ExampleError('Class "App\Coupon" not found', new Error('Class "App\Coupon" not found')));

        $back = $roundTrip(new SpecificationResult('Basket', [new ContextResult('App\Basket', [$inner]), $blocked]));

        [$outer, $erred] = $back->getResults();
        expect($outer)->toBeAnInstanceOf(ContextResult::class);
        expect($outer->getTitle())->toBe('App\Basket');
        expect($outer->getResults()[0]->getTitle())->toBe('when empty');
        expect($outer->getResults()[0]->getResults()[0]->getTitle())->toBe('totals nothing');
        expect($erred->isError())->toBeTrue();
        expect($erred->getError()->missingClass())->toBe('App\Coupon');
        expect($back->isBlockedOnMissingClass())->toBeTrue();
    });

    it("carries a feature with its path, each scenario's line and attachments, and each step's state, error, expectation, output and duration", function () use ($roundTrip) {
        try {
            $line = __LINE__ + 1;
            throw new RuntimeException('no such product');
        } catch (RuntimeException $e) {
            $stepError = new StepError('no such product', $e);
        }
        $passed = new StepResult('Given a basket', 'passed');
        $passed->setOutput("ran\n");
        $passed->setDuration(0.5);
        $failed = new StepResult('Then I pay 10', 'failure');
        $failed->setError(StepError::fromReport('Expected 9 to be 10', 'Failure'));
        $failed->setMatch(MatchResult::failed(9, 10, 'Expected 9 to be 10', __FILE__, __LINE__, null, 'toBe'));
        $errored = new StepResult('When I add a ghost', 'error');
        $errored->setError($stepError);
        $errored->setWarnings([['severity' => E_WARNING, 'message' => 'w', 'file' => __FILE__, 'line' => 1]]);
        $scenario = new ScenarioResult('Paying', [$passed, $failed, $errored, new StepResult('And later', 'skipped', 'No printer here'), new StepResult('And unknown', 'undefined'), new StepResult('And pending', 'pending', 'Needs the gateway')], 7, ['log' => 'x']);

        $back = $roundTrip(new FeatureResult('Checkout', [$scenario], 'features/checkout.feature'));

        expect($back->getTitle())->toBe('Checkout');
        expect($back->getPath())->toBe('features/checkout.feature');
        $scenario = $back->getResults()[0];
        expect($scenario->getTitle())->toBe('Paying');
        expect($scenario->getLine())->toBe(7);
        expect($scenario->getAttachments())->toBe(['log' => 'x']);
        $steps = $scenario->getResults();
        expect(array_map(static fn(StepResult $s): string => $s->getState(), $steps))->toBe(['passed', 'failure', 'error', 'skipped', 'undefined', 'pending']);
        expect($steps[0]->getOutput())->toBe("ran\n");
        expect($steps[0]->getDuration())->toBe(0.5);
        expect($steps[1]->getError()->getMessage())->toBe('Expected 9 to be 10');
        expect($steps[1]->getMatch()->getActual())->toBe(10);
        expect($steps[1]->getMatch()->getMatcher())->toBe('toBe');
        expect($steps[2]->getError()->getType())->toBe('RuntimeException');
        expect($steps[2]->getError()->getLine())->toBe($line);
        expect($steps[2]->getError()->lineIn(__FILE__))->toBe($line);
        expect($steps[2]->getWarnings())->toBe($errored->getWarnings());
        expect($steps[3]->getReason())->toBe('No printer here');
        expect($steps[4]->getReason())->toBeNull();
        expect($steps[5]->getReason())->toBe('Needs the gateway');
    });

    it("answers null for a line that is not on the wire, and true for the line that ends it", function () {
        $wire = new Wire();

        expect($wire->decode('Bootstrap loaded...'))->toBeNull();
        expect($wire->decode('{"json": "but not the wire"}'))->toBeNull();
        expect($wire->decode(''))->toBeNull();
        expect($wire->decode($wire->ending()))->toBe(true);
    });
});
